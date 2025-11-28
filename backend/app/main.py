from fastapi import FastAPI

from .api.requests import router as requests_router
from .api.users import router as users_router

# Database schema is managed via Alembic migrations.

app = FastAPI(title="Matching Service MVP")

app.include_router(users_router)
app.include_router(requests_router)


@app.get("/")
def root():
    return {"status": "ok"}
