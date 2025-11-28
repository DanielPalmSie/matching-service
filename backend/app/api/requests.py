from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from .. import models, schemas
from ..deps import get_db

router = APIRouter(prefix="/requests", tags=["requests"])


@router.post("/", response_model=schemas.RequestOut, status_code=status.HTTP_201_CREATED)
def create_request(request: schemas.RequestCreate, db: Session = Depends(get_db)):
    user = db.query(models.User).filter(models.User.id == request.user_id).first()
    if user is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")

    db_request = models.Request(**request.dict())
    db.add(db_request)
    db.commit()
    db.refresh(db_request)
    return db_request


@router.get("/{request_id}", response_model=schemas.RequestOut)
def get_request(request_id: int, db: Session = Depends(get_db)):
    db_request = db.query(models.Request).filter(models.Request.id == request_id).first()
    if db_request is None:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Request not found")
    return db_request
