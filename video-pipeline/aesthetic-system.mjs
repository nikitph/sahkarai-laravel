export const aestheticVersion = 'acharya-aesthetic/1.0.0';

export const tokens = Object.freeze({
    color: Object.freeze({
        canvasCream: '#F6F7F4',
        surfacePrimary: '#FFFFFF',
        inkPrimary: '#17232D',
        inkSecondary: '#394955',
        inkMuted: '#5F6B73',
        divider: '#CDD5D9',
        brandPrimary: '#1F5D73',
        binduSaffron: '#A66A16',
        binduSaffronSoft: '#F1E4CD',
        focusHalo: '#D9E9EF',
    }),
    layout: Object.freeze({
        canvasWidth: 1920,
        canvasHeight: 1080,
        railInset: 96,
    }),
    motion: Object.freeze({
        sceneEnterMs: 620,
        itemEnterMs: 480,
        binduMorphMs: 460,
    }),
});

const c = tokens.color;

export const compositionCss = `*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;overflow:hidden;font-family:Inter,"Segoe UI",sans-serif;color:${c.inkPrimary}}[data-composition-id]{position:relative;overflow:hidden;background:${c.canvasCream}}.canvas-bg{position:absolute;inset:0;background:${c.canvasCream};background-image:radial-gradient(circle at 12% 18%,rgba(31,93,115,.065),transparent 28%),radial-gradient(circle at 83% 77%,rgba(166,106,22,.06),transparent 32%)}.grain{position:absolute;inset:0;opacity:.25;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.72' numOctaves='3' stitchTiles='stitchTiles'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.08'/%3E%3C/svg%3E")}.brandrail{position:absolute;left:96px;right:96px;top:54px;display:flex;align-items:center;justify-content:space-between;color:${c.inkMuted};font-size:15px;letter-spacing:.13em;text-transform:uppercase}.brand{font-weight:700;color:${c.inkPrimary};letter-spacing:.18em}.progress{display:flex;gap:7px}.progress i{width:18px;height:3px;border-radius:9px;background:${c.divider}}.scene{position:absolute;inset:132px 96px 72px;opacity:0;display:flex;flex-direction:column;justify-content:center}.scene-inner{max-width:1536px;width:100%;margin:auto}.eyebrow{font-size:17px;letter-spacing:.15em;color:${c.binduSaffron};font-weight:700;margin-bottom:22px}h1{font-size:76px;line-height:1.03;letter-spacing:-.045em;max-width:1200px;margin:0 0 28px;font-weight:620}h2{font-size:54px;line-height:1.1;letter-spacing:-.035em;max-width:1250px;margin:0 0 28px;font-weight:610}.lede{font-size:27px;line-height:1.5;max-width:1120px;color:${c.inkSecondary};margin:0}.labels{display:flex;gap:14px;margin-top:36px;flex-wrap:wrap}.labels span{border:1px solid ${c.divider};background:${c.surfacePrimary};border-radius:999px;padding:13px 20px;font-size:18px}.recap{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.recap>div{min-height:210px;border:1px solid ${c.divider};background:${c.surfacePrimary};border-radius:22px;padding:28px;box-shadow:0 8px 24px rgba(31,41,55,.04)}.recap b,.equations span{font-size:16px;color:#B45309}.recap p{font-size:25px;line-height:1.4}.equations{display:flex;flex-direction:column;gap:16px}.equations>div{display:grid;grid-template-columns:56px 1fr .8fr;gap:24px;align-items:center;border:1px solid ${c.divider};background:${c.surfacePrimary};padding:24px 30px;border-radius:16px}.equations strong{font:34px/1.2 "STIX Two Math",serif}.equations small{font-size:18px;color:${c.inkMuted}}.bindu{position:absolute;inset:0;pointer-events:none}.bindu-dot{position:absolute;width:14px;height:14px;border-radius:50%;background:${c.binduSaffron};right:89px;top:65px;transform-origin:center}.bindu-line{position:absolute;width:180px;height:4px;background:${c.binduSaffron};left:96px;bottom:69px;border-radius:9px;transform-origin:left center;opacity:0}.bindu-ring{position:absolute;width:92px;height:92px;border:3px solid ${c.binduSaffron};border-radius:50%;right:8%;top:42%;opacity:0}.clip{visibility:hidden}.scene.clip{visibility:visible}`;
