# Local textbook cache

This directory is populated on the tutor machine by `python scripts/download_books.py` or `bash scripts/bootstrap_mac.sh`.

The configured sources are official eBalbharati / Maharashtra State Bureau textbook PDFs listed in `data/materials.json`.

The PDF files themselves are intentionally not committed to this public repository. Balbharati textbook pages state that the Maharashtra State Bureau reserves rights to the books and that reproduction requires permission. Keeping the repository as code + source manifest lets each installation fetch the official copies directly for local educational use.

Expected local files after bootstrap:

- `evs1.pdf` — Environmental Studies Part One, Standard Five
- `evs2.pdf` — Environmental Studies Part Two, Standard Five
- `math.pdf` — Mathematics, Standard Five
- `english.pdf` — English Balbharati, Standard Five

These PDFs are ignored by Git through `.gitignore`.
