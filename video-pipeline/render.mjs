#!/usr/bin/env node

import { Buffer } from 'node:buffer';
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { z } from 'zod';
import { aestheticVersion, compositionCss } from './aesthetic-system.mjs';

const pipelineVersion = 'sahkar-acharya/1.0.0';
const runtimeDir = dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const flag = (name, fallback) => {
    const index = args.indexOf(name);

    return index >= 0 ? args[index + 1] : fallback;
};
const lessonPath = flag('--lesson');
const outputDir = resolve(flag('--output', 'output'));
const quality = flag('--quality', 'high');
const fps = Number(flag('--fps', '30'));

if (!lessonPath) {
    throw new Error('--lesson is required');
}

if (!['draft', 'standard', 'high'].includes(quality)) {
    throw new Error(`Unsupported quality '${quality}'`);
}

if (![24, 30, 60].includes(fps)) {
    throw new Error(`Unsupported frame rate '${fps}'`);
}

const visualSpec = z.discriminatedUnion('type', [
    z
        .object({
            type: z.literal('title'),
            title: z.string().min(1),
            subtitle: z.string().optional(),
        })
        .strict(),
    z
        .object({
            type: z.literal('concept'),
            heading: z.string().min(1),
            body: z.string().min(1),
            labels: z.array(z.string()),
        })
        .strict(),
    z
        .object({
            type: z.literal('recap'),
            points: z.array(z.string().min(1)).min(1).max(3),
        })
        .strict(),
    z
        .object({
            type: z.literal('equation_derivation'),
            steps: z.array(z.string().min(1)).min(1),
            transformations: z.array(z.string()),
        })
        .strict(),
]);
const beatSchema = z
    .object({
        id: z.string().min(1),
        order: z.number().int().nonnegative(),
        learningGoal: z.string().min(1),
        narration: z
            .object({
                text: z.string().min(1),
                exact: z.literal(true),
                expectedDurationMs: z.number().positive(),
                cues: z.array(z.unknown()),
            })
            .strict(),
        delivery: z.record(z.unknown()),
        visualIntent: z.string().min(1),
        layout: z.string().min(1),
        visualSpec,
        constraints: z.record(z.unknown()),
        rendererHint: z
            .object({
                preferred: z.literal('hyperframes'),
                allowed: z.array(z.literal('hyperframes')),
                qualityTier: z.string(),
            })
            .strict(),
        binduPlan: z.record(z.unknown()),
        transitionIn: z.record(z.unknown()),
        transitionOut: z.record(z.unknown()),
    })
    .strict();
const lessonSchema = z
    .object({
        version: z.literal('1.0-no-avatar'),
        id: z.string().min(1),
        title: z.string().min(1),
        subject: z.string().min(1),
        gradeLevel: z.string().min(1),
        language: z.string().min(1),
        learningObjectives: z.array(
            z.object({ id: z.string(), description: z.string() }).strict(),
        ),
        prerequisites: z.array(z.string()),
        estimatedDurationMs: z.number().positive(),
        presentation: z
            .object({ branding: z.literal('acharya'), headerLabel: z.string() })
            .strict(),
        narrationProfile: z
            .object({
                provider: z.enum(['elevenlabs', 'fake']),
                voiceId: z.string(),
                modelId: z.string(),
                language: z.string(),
                normalizationVersion: z.string(),
            })
            .strict(),
        beats: z.array(beatSchema).min(2),
        metadata: z.record(z.unknown()),
    })
    .strict();

const canonicalize = (value) =>
    Array.isArray(value)
        ? value.map(canonicalize)
        : value && typeof value === 'object'
          ? Object.fromEntries(
                Object.entries(value)
                    .sort(([a], [b]) => a.localeCompare(b))
                    .map(([key, child]) => [key, canonicalize(child)]),
            )
          : value;
const sha256 = (value) => createHash('sha256').update(value).digest('hex');
const canonicalJson = (value) => JSON.stringify(canonicalize(value));
const escapeHtml = (value) =>
    String(value).replace(
        /[&<>"']/g,
        (character) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[character],
    );
const run = (command, commandArgs, options = {}) =>
    new Promise((resolvePromise, reject) => {
        const child = spawn(command, commandArgs, {
            cwd: options.cwd,
            env: { ...process.env, ...options.env },
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        let stdout = '';
        let stderr = '';
        child.stdout.on('data', (chunk) => {
            stdout += String(chunk);
        });
        child.stderr.on('data', (chunk) => {
            stderr += String(chunk);
        });
        child.on('error', reject);
        child.on('exit', (code) =>
            code === 0
                ? resolvePromise({ stdout, stderr })
                : reject(
                      new Error(
                          `${command} failed (${code}): ${(stdout + '\n' + stderr).slice(-4000)}`,
                      ),
                  ),
        );
    });

const rawLesson = JSON.parse(await readFile(resolve(lessonPath), 'utf8'));
const lesson = lessonSchema.parse({
    ...rawLesson,
    beats: [...rawLesson.beats].sort((a, b) => a.order - b.order),
});
const inputHash = sha256(
    canonicalJson({ lesson, pipelineVersion, aestheticVersion, fps, quality }),
);
const compositionDir = join(outputDir, 'composition');
const narrationDir = join(outputDir, 'narration');
await mkdir(compositionDir, { recursive: true });
await mkdir(narrationDir, { recursive: true });

function silentWav(durationMs, sampleRate = 48000) {
    const sampleCount = Math.round((durationMs / 1000) * sampleRate);
    const dataBytes = sampleCount * 2;
    const buffer = Buffer.alloc(44 + dataBytes);
    buffer.write('RIFF', 0);
    buffer.writeUInt32LE(36 + dataBytes, 4);
    buffer.write('WAVE', 8);
    buffer.write('fmt ', 12);
    buffer.writeUInt32LE(16, 16);
    buffer.writeUInt16LE(1, 20);
    buffer.writeUInt16LE(1, 22);
    buffer.writeUInt32LE(sampleRate, 24);
    buffer.writeUInt32LE(sampleRate * 2, 28);
    buffer.writeUInt16LE(2, 32);
    buffer.writeUInt16LE(16, 34);
    buffer.write('data', 36);
    buffer.writeUInt32LE(dataBytes, 40);

    return buffer;
}

async function narrate(beat, index) {
    const prefix = `beat-${String(index).padStart(2, '0')}`;

    if (lesson.narrationProfile.provider === 'fake') {
        const path = join(narrationDir, `${prefix}.wav`);
        await writeFile(path, silentWav(beat.narration.expectedDurationMs));

        return {
            beatId: beat.id,
            path,
            durationMs: beat.narration.expectedDurationMs,
            provider: 'fake',
            cacheHit: false,
            characters: beat.narration.text.length,
        };
    }

    const apiKey = process.env.ELEVENLABS_API_KEY;

    if (!apiKey) {
        throw new Error('ELEVENLABS_API_KEY is required for video narration');
    }

    const response = await fetch(
        `https://api.elevenlabs.io/v1/text-to-speech/${encodeURIComponent(lesson.narrationProfile.voiceId)}/with-timestamps?output_format=mp3_44100_128`,
        {
            method: 'POST',
            headers: {
                'content-type': 'application/json',
                'xi-api-key': apiKey,
            },
            body: JSON.stringify({
                text: beat.narration.text,
                model_id: lesson.narrationProfile.modelId,
                language_code: lesson.language.split('-')[0],
                voice_settings: {
                    stability: 0.62,
                    similarity_boost: 0.78,
                    style: 0.2,
                    use_speaker_boost: true,
                    speed: 0.92,
                },
            }),
        },
    );

    if (!response.ok) {
        throw new Error(
            `ElevenLabs narration failed (${response.status}): ${(await response.text()).slice(-2000)}`,
        );
    }

    const payload = await response.json();
    const path = join(narrationDir, `${prefix}.mp3`);
    await writeFile(path, Buffer.from(payload.audio_base64, 'base64'));
    const alignment = payload.normalized_alignment ?? payload.alignment;
    const durationMs = alignment?.character_end_times_seconds?.length
        ? Math.round(alignment.character_end_times_seconds.at(-1) * 1000)
        : beat.narration.expectedDurationMs;

    return {
        beatId: beat.id,
        path,
        durationMs,
        provider: 'elevenlabs',
        cacheHit: false,
        characters: beat.narration.text.length,
    };
}

const narration = [];

for (const [index, beat] of lesson.beats.entries()) {
    narration.push(await narrate(beat, index));
}

const masterAudio = join(compositionDir, 'narration-master.wav');
const ffmpegInputs = narration.flatMap((item) => ['-i', item.path]);
const concatInputs = narration.map((_, index) => `[${index}:a]`).join('');
await run('ffmpeg', [
    '-hide_banner',
    '-loglevel',
    'error',
    '-y',
    ...ffmpegInputs,
    '-filter_complex',
    `${concatInputs}concat=n=${narration.length}:v=0:a=1[a]`,
    '-map',
    '[a]',
    '-ar',
    '48000',
    '-ac',
    '1',
    masterAudio,
]);
const durationMs = narration.reduce((sum, item) => sum + item.durationMs, 0);

function markup(beat) {
    const spec = beat.visualSpec;

    if (spec.type === 'title') {
        return `<div class="eyebrow">${escapeHtml(beat.learningGoal)}</div><h1>${escapeHtml(spec.title)}</h1>${spec.subtitle ? `<p class="lede">${escapeHtml(spec.subtitle)}</p>` : ''}`;
    }

    if (spec.type === 'concept') {
        return `<div class="eyebrow">CORE IDEA</div><h2>${escapeHtml(spec.heading)}</h2><p class="lede">${escapeHtml(spec.body)}</p><div class="labels">${spec.labels.map((label) => `<span>${escapeHtml(label)}</span>`).join('')}</div>`;
    }

    if (spec.type === 'recap') {
        return `<div class="eyebrow">RECAP</div><h2>What to retain</h2><div class="recap">${spec.points.map((point, index) => `<div><b>0${index + 1}</b><p>${escapeHtml(point)}</p></div>`).join('')}</div>`;
    }

    return `<div class="eyebrow">IMPORTANT DATES</div><div class="equations">${spec.steps.map((step, index) => `<div><span>${String(index + 1).padStart(2, '0')}</span><strong>${escapeHtml(step)}</strong><small>${escapeHtml(spec.transformations[index] ?? '')}</small></div>`).join('')}</div>`;
}

let sceneCursor = 0;
const scenes = lesson.beats
    .map((beat, index) => {
        const start = sceneCursor;
        const duration = narration[index].durationMs;
        sceneCursor += duration;

        return `<section id="scene-${escapeHtml(beat.id)}" class="scene clip scene-${index}" data-start="${(start / 1000).toFixed(3)}" data-duration="${(duration / 1000).toFixed(3)}" data-track-index="0"><div class="scene-inner">${markup(beat)}</div></section>`;
    })
    .join('\n');
let timelineCursor = 0;
const timeline = lesson.beats
    .map((beat, index) => {
        const start = timelineCursor / 1000;
        const duration = narration[index].durationMs / 1000;
        timelineCursor += narration[index].durationMs;
        const prior = index
            ? `tl.set('.scene-${index - 1}',{opacity:0},${start.toFixed(3)});`
            : '';
        const active = beat.binduPlan.mode;

        return `${prior}tl.set('.scene-${index}',{opacity:1},${start.toFixed(3)});tl.fromTo('.scene-${index} .scene-inner',{y:24,opacity:0},{y:0,opacity:1,duration:.62,ease:'power3.out'},${(start + 0.05).toFixed(3)});tl.fromTo('.scene-${index} .scene-inner>*',{y:16,opacity:0},{y:0,opacity:1,duration:.48,stagger:.09,ease:'power2.out'},${(start + 0.18).toFixed(3)});tl.fromTo('.bindu-dot',{scale:.96,opacity:.68},{scale:1.06,opacity:.82,duration:1.2,yoyo:true,repeat:1,ease:'sine.inOut'},${(start + 0.08).toFixed(3)});${active === 'underline' ? `tl.fromTo('.bindu-line',{scaleX:0,opacity:0},{scaleX:1,opacity:1,duration:.46,ease:'power2.out'},${(start + Math.min(1.1, duration * 0.25)).toFixed(3)});tl.to('.bindu-line',{scaleX:0,opacity:0,duration:.36,ease:'power2.in'},${(start + Math.max(1.8, duration - 0.55)).toFixed(3)});` : `tl.fromTo('.bindu-ring',{scale:.7,opacity:0},{scale:1,opacity:1,duration:.48,ease:'power2.out'},${(start + Math.min(1.1, duration * 0.25)).toFixed(3)});tl.to('.bindu-ring',{scale:.7,opacity:0,duration:.36,ease:'power2.in'},${(start + Math.max(1.8, duration - 0.55)).toFixed(3)});`}`;
    })
    .join('\n');

const html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>${escapeHtml(lesson.title)}</title><style>${compositionCss}</style></head><body><main data-composition-id="sahkar-${escapeHtml(lesson.id)}" data-start="0" data-duration="${(durationMs / 1000).toFixed(3)}" data-track-index="99" data-width="1920" data-height="1080"><div class="canvas-bg"></div><div class="grain"></div><header class="brandrail"><span class="brand">${escapeHtml(lesson.presentation.headerLabel)}</span><div class="progress">${lesson.beats.map(() => '<i></i>').join('')}</div><span>${escapeHtml(lesson.subject)} · ${escapeHtml(lesson.gradeLevel)}</span></header>${scenes}<div class="bindu"><div class="bindu-dot"></div><div class="bindu-line"></div><div class="bindu-ring"></div></div><audio id="narration-master" class="clip" src="./narration-master.wav" data-start="0" data-duration="${(durationMs / 1000).toFixed(3)}" data-track-index="1"></audio></main><script src="./gsap.min.js"></script><script>const tl=gsap.timeline({paused:true});tl.set('.scene',{opacity:0},0);${timeline}window.__timelines=window.__timelines||{};window.__timelines['sahkar-${escapeHtml(lesson.id)}']=tl;</script></body></html>`;
await writeFile(join(compositionDir, 'index.html'), html);
await copyFile(
    join(runtimeDir, 'node_modules', 'gsap', 'dist', 'gsap.min.js'),
    join(compositionDir, 'gsap.min.js'),
);
await writeFile(
    join(compositionDir, 'meta.json'),
    JSON.stringify(
        {
            id: `sahkar-${lesson.id}`,
            name: lesson.title,
            width: 1920,
            height: 1080,
            fps,
            duration: durationMs / 1000,
        },
        null,
        2,
    ),
);
const hyperframes = join(runtimeDir, 'node_modules', '.bin', 'hyperframes');
await run(hyperframes, ['lint'], {
    cwd: compositionDir,
    env: { HYPERFRAMES_NO_UPDATE_CHECK: '1', CI: '1' },
});
await run(
    hyperframes,
    [
        'render',
        '--output',
        join(outputDir, 'final.mp4'),
        '--fps',
        String(fps),
        '--quality',
        quality,
        '--no-browser-gpu',
        '--quiet',
    ],
    { cwd: compositionDir, env: { HYPERFRAMES_NO_UPDATE_CHECK: '1', CI: '1' } },
);
const manifest = {
    product: 'SahkarAI Explainers',
    pipelineVersion,
    aestheticVersion,
    inputHash,
    durationMs,
    outputProfile: {
        width: 1920,
        height: 1080,
        fps,
        quality,
        colorSpace: 'rec709',
        audioSampleRate: 48000,
    },
    lesson: {
        id: lesson.id,
        title: lesson.title,
        locale: lesson.metadata.locale,
        documentVersionId: lesson.metadata.documentVersionId,
    },
    routing: lesson.beats.map((beat) => ({
        beatId: beat.id,
        renderer: 'hyperframes',
        reason: 'Regulatory V1 preserves the approved Sahkar/Acharya composition grammar.',
        policyVersion: 'sahkar-router/1.0.0',
    })),
    narration: narration.map((item) => ({
        beatId: item.beatId,
        provider: item.provider,
        durationMs: item.durationMs,
        characters: item.characters,
        cacheHit: item.cacheHit,
    })),
    provenance: {
        lessonHash: sha256(canonicalJson(lesson)),
        aestheticVersion,
        renderer: `hyperframes/0.7.86`,
        generatedAt: new Date().toISOString(),
    },
};
await writeFile(
    join(outputDir, 'build-manifest.json'),
    `${JSON.stringify(manifest, null, 2)}\n`,
);
console.log(
    JSON.stringify({
        videoPath: join(outputDir, 'final.mp4'),
        manifestPath: join(outputDir, 'build-manifest.json'),
        durationMs,
        inputHash,
        pipelineVersion,
    }),
);
