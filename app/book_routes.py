from functools import lru_cache
from io import BytesIO

import requests
from fastapi import APIRouter, HTTPException, Query, Response

from .combined_knowledge import CBSE_SUBJECTS

router = APIRouter(prefix="/api", tags=["books"])

PDF_HEADERS = {
    "User-Agent": "Mozilla/5.0 (compatible; ChaloPadhaye/1.0; +https://chalopadhaye.vercel.app)",
    "Accept": "application/pdf,*/*;q=0.8",
}


def _book_url(grade: int, subject: str, chapter: int):
    meta = CBSE_SUBJECTS.get(int(grade), {}).get(subject)
    if not meta:
        raise HTTPException(404, "Textbook source is not mapped for this class and subject")
    if chapter < 1 or chapter > int(meta["chapters"]):
        raise HTTPException(404, "Chapter is outside the mapped textbook")
    return meta, f"https://www.ncert.nic.in/textbook/pdf/{meta['prefix']}{chapter:02d}.pdf"


@lru_cache(maxsize=24)
def _fetch_pdf(grade: int, subject: str, chapter: int):
    meta, url = _book_url(grade, subject, chapter)
    try:
        r = requests.get(url, headers=PDF_HEADERS, timeout=30, allow_redirects=True)
    except Exception as exc:
        raise HTTPException(502, "Could not reach the official NCERT textbook server") from exc
    body = r.content or b""
    if not r.ok or b"%PDF" not in body[:32]:
        raise HTTPException(502, f"Official NCERT textbook returned an invalid response ({r.status_code})")
    return meta, url, body


@router.get("/book-page-image")
def book_page_image(
    grade: int = Query(..., ge=1, le=12),
    subject: str = Query(..., min_length=1, max_length=40),
    chapter: int = Query(..., ge=1, le=30),
    page: int = Query(..., ge=1, le=200),
):
    """Render one allow-listed official NCERT PDF page as a same-origin PNG.

    This avoids third-party iframe restrictions while keeping the official PDF
    as the source of truth. The endpoint is not an open URL proxy.
    """
    try:
        import fitz  # PyMuPDF
    except Exception as exc:
        raise HTTPException(503, "Textbook page renderer is not installed") from exc

    meta, url, pdf_bytes = _fetch_pdf(int(grade), subject, int(chapter))
    try:
        doc = fitz.open(stream=BytesIO(pdf_bytes), filetype="pdf")
        if page > doc.page_count:
            raise HTTPException(404, f"This chapter has {doc.page_count} PDF pages")
        pdf_page = doc.load_page(page - 1)
        # 1.55x gives readable textbook text without creating an oversized response.
        pix = pdf_page.get_pixmap(matrix=fitz.Matrix(1.55, 1.55), alpha=False)
        png = pix.tobytes("png")
        total_pages = doc.page_count
        doc.close()
    except HTTPException:
        raise
    except Exception as exc:
        raise HTTPException(502, "Could not render this textbook page") from exc

    return Response(
        content=png,
        media_type="image/png",
        headers={
            "Cache-Control": "public, max-age=86400, stale-while-revalidate=604800",
            "X-Book-Page-Count": str(total_pages),
            "X-Book-Source": url,
            "X-Book-Title": meta["title"],
        },
    )


@router.get("/book-source")
def book_source(
    grade: int = Query(..., ge=1, le=12),
    subject: str = Query(..., min_length=1, max_length=40),
    chapter: int = Query(..., ge=1, le=30),
):
    """Return trusted metadata for one mapped CBSE/NCERT chapter."""
    meta, url = _book_url(int(grade), subject, int(chapter))
    return {
        "title": meta["title"],
        "chapter": chapter,
        "url": url,
        "official": True,
        "publisher": "NCERT",
    }
