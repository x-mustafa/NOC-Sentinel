"""
Password hashing utilities using bcrypt directly.
Handles PHP's $2y$ prefix compatibility.
"""
import bcrypt


def hash_password(plain: str) -> str:
    """Hash a password using bcrypt ($2b$ prefix)."""
    hashed = bcrypt.hashpw(plain.encode("utf-8"), bcrypt.gensalt(rounds=10))
    return hashed.decode("utf-8")


def verify_password(plain: str, hashed: str) -> bool:
    """
    Verify a password against a hash.
    Handles both $2b$ (Python) and $2y$ (PHP) prefixes.
    """
    if not plain or not hashed:
        return False
    # PHP uses $2y$, Python bcrypt uses $2b$ — they're identical algorithms
    hashed_bytes = hashed.encode("utf-8").replace(b"$2y$", b"$2b$", 1)
    try:
        return bcrypt.checkpw(plain.encode("utf-8"), hashed_bytes)
    except Exception:
        return False
