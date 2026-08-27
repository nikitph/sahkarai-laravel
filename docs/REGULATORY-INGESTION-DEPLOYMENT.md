# Regulatory archive and resilient extraction deployment

## What this release changes

The regulatory corpus now has two ingestion paths which converge on the same immutable document/version schema:

- twice-daily observers discover new RBI, Income Tax, GST, CBIC, and NABARD publications;
- `regulatory:archive-import` validates and imports the frozen six-year PDF archive without redownloading it.

Every acquired version first uses local text extraction. A platform PDF which yields no usable text is sent to Kimi's Files API with purpose `file-extract`. The remote Kimi file is deleted after the response, whether extraction succeeds or fails. Every native and Kimi attempt is retained in `extraction_attempts`. Exhausting Kimi's three attempts moves the version to `needs_review`; the original remains stored but is not visible to customers.

Interpretations become customer-visible as soon as the English locale succeeds. Hindi, Gujarati, and Marathi are retried independently up to two more times (three attempts total per locale). A document with English plus missing translations is marked `partial` and the UI falls back to English. A version whose English interpretation never succeeds remains private to operations. A failed new revision never displaces an older published revision.

## New production configuration

Create a Moonshot/Kimi API key in the Kimi platform. Add it as the protected GitHub Environment secret `KIMI_API_KEY`; for manual Kamal deployments add the same value to the gitignored `.kamal/secrets` file. Never put the key in `.env.example`, a manifest, an image layer, a log, or a command argument.

The production deployment enables these non-secret settings in `config/deploy.yml`:

```dotenv
KIMI_OCR_ENABLED=true
KIMI_OCR_TIMEOUT=180
KIMI_OCR_ATTEMPTS=3
```

The application accepts PDFs up to `REGULATORY_MAX_DOCUMENT_BYTES` (50 MiB by default), below Kimi's current Files API limit. Keep the normal queue worker timeout above the Kimi HTTP timeout; this release sets the worker timeout to 600 seconds and Redis retry-after to 660 seconds.

DeepSeek remains the interpretation and chat provider. The recommended production model is `deepseek-v4-flash`; Kimi is used only for Tier 2 text extraction after the native parser fails. Private user uploads are not sent to Kimi.

## Preserve the seed archive

The initial local archive is intentionally Git-untracked:

```text
storage/app/regulatory-circulars-2021-2026/
├── cbic/          293 PDFs
├── income_tax/    110 PDFs
├── nabard/        324 PDFs
└── rbi/         1,241 PDFs
```

At handoff it contains 1,968 downloaded PDFs (about 1.3 GiB), each represented by a JSONL manifest row with source identity, dates, source/download URLs, relative path, byte count, and SHA-256. Treat the directory as a portable cold archive. Before importing production data, copy it unchanged to a versioned, private DigitalOcean Spaces prefix such as `cold-archive/regulatory-circulars-2021-2026/`. Enable bucket versioning and retain the local copy until reconciliation finishes.

Example operator-side copy (credentials supplied through the environment):

```bash
aws s3 sync \
  storage/app/regulatory-circulars-2021-2026/ \
  s3://$SPACES_BUCKET_NAME/cold-archive/regulatory-circulars-2021-2026/ \
  --endpoint-url "$SPACES_ENDPOINT" \
  --no-progress
```

The cold archive and the application's canonical `originals/...` objects serve different purposes: the former is the portable evidence package; the latter is the live object layout referenced by PostgreSQL. Do not point database rows directly at arbitrary cold-archive paths.

## Pre-deployment checks

1. Add the `KIMI_API_KEY` secret and confirm the Spaces settings already required by the application.
2. Deploy to staging. The migration adds extraction provenance and notification settings; it does not rewrite existing documents.
3. Use a representative image-only PDF to exercise upload, extraction, and remote cleanup:

   ```bash
   php artisan sahkarai:kimi:verify /secure/path/to/image-only-sample.pdf
   ```

4. Run the normal provider verification for DeepSeek and Razorpay:

   ```bash
   php artisan sahkarai:providers:verify
   ```

5. Confirm that the scheduler and standard worker are running and that the worker uses a 600-second timeout.

## Import the historical archive

Stage the unchanged archive directory on a trusted host or temporarily inside the worker container. Validate every manifest and checksum before writing:

```bash
php artisan regulatory:archive-import \
  /secure/staging/regulatory-circulars-2021-2026 \
  --dry-run \
  --report=/secure/staging/archive-validation.json
```

Import after a successful dry run:

```bash
php artisan regulatory:archive-import \
  /secure/staging/regulatory-circulars-2021-2026 \
  --report=/secure/staging/archive-import.json
```

The importer is idempotent on `(source, source_document_id, sha256)`, verifies byte counts and checksums before persistence, marks rows as backfill, and suppresses historical notification fan-out. It queues extraction rather than holding the import process open. Running it again reports existing content as unchanged. Use `--source=...`, `--year=...`, or `--limit=...` for a canary; use `--sync` only for a small diagnostic sample.

Keep the import report with the archive. Reconcile these totals before removing the staging copy:

```text
validated = 1968
failed    = 0
created + unchanged = 1968
```

## Ongoing operation

The scheduler polls each source twice daily. Source failures are recorded in `poll_runs`; three consecutive failed runs create an ops alert. Acquired bytes are checksum-deduplicated, so repeated observations do not create revisions. Changed bytes create a linked immutable revision.

Review terminal extraction failures on the ops dashboard. After correcting a provider/configuration issue or confirming the source file, retry the full pipeline or Kimi directly:

```bash
php artisan regulatory:extraction-retry <document-version-id>
php artisan regulatory:extraction-retry <document-version-id> --kimi
```

Use `--sync` only during an attended diagnostic. Never manually set `is_public`; publication is controlled by successful extraction plus a validated English interpretation.

## Rollback and recovery

An application rollback may leave this additive migration in place; the previous release ignores the extra columns and table. Do not run `migrate:rollback` after extraction attempts have been recorded unless their audit history has first been exported. Originals and the cold archive are immutable recovery inputs, PostgreSQL is the index and workflow state, and extracted text can be regenerated from originals.

If Kimi is unavailable, set `KIMI_OCR_ENABLED=false` and redeploy. Native extraction and source acquisition continue; native failures move to `needs_review` rather than becoming public. Restoring Kimi later and running `regulatory:extraction-retry` resumes those versions without redownloading their originals.
