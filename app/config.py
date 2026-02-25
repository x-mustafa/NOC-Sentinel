from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    db_host: str = "localhost"
    db_port: int = 3306
    db_user: str = "root"
    db_pass: str = ""
    db_name: str = "tabadul_noc"
    app_secret: str = "change_this_to_a_random_string_at_least_32_chars_long"
    session_max_age: int = 86400 * 7  # 7 days

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"


settings = Settings()
