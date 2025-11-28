import os
import sys
from logging.config import fileConfig
from pathlib import Path

from alembic import context
from sqlalchemy import engine_from_config, pool

# Ensure the backend/ directory is on the Python path for imports
sys.path.append(str(Path(__file__).resolve().parents[1]))

from app.db import Base, DATABASE_URL as DB_DATABASE_URL  # noqa: E402
from app import models  # noqa: F401,E402

config = context.config

if config.config_file_name is not None:
    fileConfig(config.config_file_name)


def get_database_url() -> str:
    """Return the database URL, preferring a configured environment variable."""
    return os.environ.get("DATABASE_URL", DB_DATABASE_URL)


target_metadata = Base.metadata


# Interpret the config file for Python logging.
# This line sets up loggers basically.


def run_migrations_offline() -> None:
    """Run migrations in 'offline' mode."""
    url = get_database_url()
    context.configure(
        url=url,
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
    )

    with context.begin_transaction():
        context.run_migrations()


def run_migrations_online() -> None:
    """Run migrations in 'online' mode."""
    configuration = config.get_section(config.config_ini_section, {})
    configuration["sqlalchemy.url"] = get_database_url()

    connectable = engine_from_config(
        configuration,
        prefix="sqlalchemy.",
        poolclass=pool.NullPool,
    )

    with connectable.connect() as connection:
        context.configure(connection=connection, target_metadata=target_metadata)

        with context.begin_transaction():
            context.run_migrations()


# Run migrations from the backend/ directory with:
# alembic upgrade head


if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
