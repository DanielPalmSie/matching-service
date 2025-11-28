from datetime import datetime
from typing import Optional

from pydantic import BaseModel


class UserBase(BaseModel):
    telegram_id: Optional[str] = None
    display_name: str
    home_city: str
    home_country: str
    timezone: str


class UserCreate(UserBase):
    pass


class UserOut(UserBase):
    id: int
    created_at: datetime

    class Config:
        orm_mode = True


class RequestBase(BaseModel):
    user_id: int
    raw_text: str
    type: str
    city: str
    country: str
    status: str = "active"


class RequestCreate(RequestBase):
    pass


class RequestOut(RequestBase):
    id: int
    created_at: datetime

    class Config:
        orm_mode = True
