## Deep-AmPEP30 Microservice API Guide

This microservice exposes antimicrobial peptide (AMP) prediction endpoints based on the AmPEP30 models. The canonical runtime uses `microservice/api/ampep30_final_api.R`.

### Quickstart

#### Local (R installed)

```bash
R -e "pr <- plumber::plumb('microservice/api/ampep30_final_api.R'); pr$run(host='0.0.0.0', port=8002)"
```

- Default port: `8002`
- Health check: `GET http://localhost:8002/health`

#### Docker

```bash
docker build -f microservice/docker/Dockerfile -t deep-ampep30 .
docker run --rm -e PLUMBER_PORT=8002 -p 8002:8002 deep-ampep30
```

#### Docker Compose

```bash
docker compose -f microservice/docker/docker-compose.yml up --build
```

- Compose default port mapping: `8002:8002` (overrides PLUMBER_PORT to 8002)

### Environment variables

| Name | Default | Description |
|---|---|---|
| `APP_ROOT` | `/app` (in container) | Application root used by startup script |
| `PLUMBER_HOST` | `0.0.0.0` | Bind address |
| `PLUMBER_PORT` | `8002` | HTTP port; Compose sets `8002` |

Note: Advanced configs in `microservice/config/config.R` apply to the alternative router `microservice/api/plumber.R`. The canonical final API (`ampep30_final_api.R`) primarily honors the host/port variables above.

### API Reference (Final API)

Base URL depends on how you run the service:
- Local: `http://localhost:8002`
- Compose: `http://localhost:8002`

#### GET /health
Basic liveness check.

Response example:
```json
{
  "status": "healthy",
  "service": "AmPEP30-Final-API",
  "version": "1.0.0",
  "timestamp": "2025-01-01T12:00:00+0000"
}
```

#### POST /predict/single
Predict one peptide sequence.

Parameters (form-encoded or query):
- `sequence` (string, required): 5–30 amino acids, allowed: `ACDEFGHIKLMNPQRSTVWY`
- `name` (string, optional): sequence name, default `query`
- `method` (string, optional): `rf` or `cnn` (default `rf`)
- `precision` (integer, optional): 0–6 decimal places (default `3`)

Curl example:
```bash
curl -X POST 'http://localhost:8002/predict/single' \
  --data-urlencode 'sequence=ACDEFGHIKLMNPQRSTVWY' \
  -d 'name=seq1' -d 'method=rf' -d 'precision=3'
```

Success response example:
```json
{
  "sequence_name": "seq1",
  "sequence": "ACDEFGHIKLMNPQRSTVWY",
  "length": 20,
  "prediction": "AMP",
  "amp_probability": 0.873,
  "non_amp_probability": 0.127,
  "confidence": 0.873,
  "model_used": "rf",
  "interpretation": "High probability of AMP",
  "status": "success"
}
```

Error response example:
```json
{
  "status": "error",
  "message": "未知的 method: x, 可用值: rf, cnn",
  "timestamp": "2025-01-01T12:00:00+0000"
}
```

#### POST /predict/fasta
Predict multiple sequences from a FASTA string.

Parameters (form-encoded or query):
- `fasta_content` (string, required): FASTA with headers (`>`)
- `method` (string, optional): `rf` or `cnn` (default `rf`)
- `precision` (integer, optional): 0–6 (default `3`)

Curl example (from file):
```bash
curl -X POST 'http://localhost:8002/predict/fasta' \
  --data-urlencode 'fasta_content@./test.fasta' \
  -d 'method=rf' -d 'precision=3'
```

Success response example:
```json
{
  "status": "success",
  "total_sequences": 3,
  "results": [
    {
      "sequence_name": "Magainin-2",
      "sequence": "GIGKFLHSAKKFGKAFVGEIMNS",
      "length": 23,
      "prediction": "AMP",
      "amp_probability": 0.912,
      "non_amp_probability": 0.088,
      "confidence": 0.912,
      "model_used": "rf",
      "interpretation": "High probability of AMP",
      "status": "success"
    }
  ],
  "timestamp": "2025-01-01T12:00:00+0000"
}
```

#### GET /model/info
Model and input constraints.

Response example:
```json
{
  "model_type": "Random Forest / CNN (Keras)",
  "model_file": "RF: AmPEP30-RF-1200tree.mdl; CNN: AmPEP30-CNN.mdl",
  "features": "RF: PSEKRAAc (偽氨基酸組成); CNN: PSEKRAAc + CNN",
  "sequence_length_range": "5-30 amino acids",
  "supported_amino_acids": "ACDEFGHIKLMNPQRSTVWY",
  "training_data_size": "3298 sequences",
  "timestamp": "2025-01-01T12:00:00+0000"
}
```

#### GET /test/demo
Returns demo predictions for known sequences (for quick verification).

### Alternative router (advanced)

`microservice/api/plumber.R` exposes a consolidated `/api/predict` endpoint with `method` switching and additional health details:
- `GET /health`
- `GET /health/detailed`
- `POST /api/predict` (JSON body: `{ "fasta": "...", "method": "rf|cnn|auto" }`)
- `POST /api/predict/rf`
- `POST /api/predict/deep`

Use this router only if you specifically need its features. The default images/entrypoints start the final API (`ampep30_final_api.R`).

