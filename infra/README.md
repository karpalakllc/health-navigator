# Infrastructure (`infra/`)

R1 deploy assets (PaaS-first, not Kubernetes):

- [deploy.md](./deploy.md) — staging/production runbook
- [env.staging.example](./env.staging.example) / [env.production.example](./env.production.example) — env reference
- [docker-compose.redis.yml](./docker-compose.redis.yml) — optional local Redis
- [docker-compose.meilisearch.yml](./docker-compose.meilisearch.yml) — optional local Meilisearch

Architecture: [docs/architecture.md](../docs/architecture.md). Roadmap: [docs/roadmap.md](../docs/roadmap.md).

## Optional: media on local object storage (MinIO)

Local development uses the `public` disk and needs nothing extra. To exercise the
object-storage path (`MEDIA_DISK=s3`) before a deploy, run MinIO in Docker:

```bash
docker run -d --name zdravje-minio -p 9000:9000 -p 9001:9001 \
  -e MINIO_ROOT_USER=minioadmin -e MINIO_ROOT_PASSWORD=minioadmin \
  minio/minio server /data --console-address :9001

# Bucket, plus anonymous read on the media/ prefix only (like the production policy)
docker exec zdravje-minio mc alias set local http://127.0.0.1:9000 minioadmin minioadmin
docker exec zdravje-minio mc mb local/zdravje-media
docker exec zdravje-minio mc anonymous set download local/zdravje-media/media
```

`apps/api/.env`:

```env
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=zdravje-media
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=http://127.0.0.1:9000/zdravje-media
```

`apps/web/.env.local`: `NEXT_PUBLIC_MEDIA_URL=http://127.0.0.1:9000/zdravje-media`
(http is accepted outside production only). Then `php artisan config:clear` and
restart `next dev`. Remove the variables to go back to the `public` disk.
