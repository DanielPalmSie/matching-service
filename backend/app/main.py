from fastapi import FastAPI
from .db import Base, engine
from .api.users import router as users_router
from .api.requests import router as requests_router

# временно, пока нет Alembic
Base.metadata.create_all(bind=engine)

app = FastAPI(title="Matching Service MVP")

app.include_router(users_router)
app.include_router(requests_router)


@app.get("/")
def root():
    return {"message": "Backend is running"}
