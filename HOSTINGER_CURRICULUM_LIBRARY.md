# Hostinger syllabus and textbook coverage — 2026-10-11

The previous syllabus card was deliberately hidden outside Telangana, despite four board modes. Learning-plan metadata only covered Maharashtra Classes 3/5, NIOS Classes 3/5, CBSE Classes 1/9/10 and Telangana Classes 1–10. Textbook selection also guessed NCERT chapter PDF filenames from lesson order, used older hardcoded Balbharati filenames, and labelled Balbharati supplements as NIOS sources. These are not reliable prescribed-book mappings.

This release adds an independent public `/api/curriculum-library` and a visible library in all four study modes. Parents/students can browse any board/class without changing their child's profile or school mode. Search and language filters work independently of disabled textbook tutoring. The existing Telangana `/api/syllabus` endpoint remains compatible.

## Imported publisher coverage

| Board | Class choices | Book entries | Syllabus documents |
|---|---|---|---|
| CBSE / NCERT | 1–12 | 1,090 publisher book selectors, including translations and vocational books | 2026–27 Class IX, X and XI–XII subject PDFs from CBSE's curriculum page |
| Maharashtra / Balbharati | 1–12 | 239 unique PDFs from all 20 pages of the English-medium, textbook, syllabus-year 2026 filter | Official board website for SSC/HSC; separate current examination documents pending |
| Telangana / SCERT | 1–10 | Official directory and subject checklist; 3 archived Class 10 reference books/outlines | Current exam syllabus and direct mappings pending |
| NIOS | 1–12 equivalent | OBE A/B/C official English materials plus Secondary and Senior Secondary subject selectors | OBE level reference curricula and archived Secondary 2020 curriculum |

NIOS repeats shared level/course resources across equivalent class choices: A covers I–III, B IV–V, C VI–VIII; Secondary is Class X equivalent and Senior Secondary Class XII equivalent. Counts are catalogue entries, not unique books, verified downloads, or teaching-ready content. Balbharati includes district editions. Medium labels describe the publisher filter, not the language of every language-subject book.

NCERT Class 3 and 5 now include Santoor, Maths Mela/Math-Mela, The World Around Us, arts and physical education using the publisher's actual selector URLs. Archived commented-out Marigold/Math-Magic mappings are excluded. NCERT links open its chapter/download catalogue instead of constructing chapter paths. Balbharati's current publisher listing uses `ebooks.ebalbharati.in`; Class 3 mathematics is `303060004.pdf`, replacing the older hardcoded mapping. English subject labels are searchable; the original publisher wording is retained alongside translated display labels.

## Sources and provenance

- NCERT: https://www.ncert.nic.in/textbook.php?ln=en
- CBSE: https://cbseacademic.nic.in/curriculum_2027.html
- Balbharati: https://books.ebalbharati.in/ (textbook `101`, English medium `303`, year `2026`; 239 records / 20 pages)
- Maharashtra board: https://www.mahahsscboard.in/
- Telangana: existing SCERT directory and syllabus references in `TELANGANA_SYLLABUS.md`
- NIOS levels/curricula: https://rcpatna.nios.ac.in/open-basic-educationobe.html
- NIOS OBE materials: https://rcpune.nios.ac.in/open-basic-education-courses-material.html
- NIOS Secondary: https://rcpune.nios.ac.in/page/secondary-courses-material.html
- NIOS Senior Secondary: https://rcpune.nios.ac.in/page/senior-secondary-courses-material.html

`data/curriculum_sources.json` records review date, per-entry provenance/status, edition cautions and input HTML SHA-256 digests. `scripts/import_curriculum_sources.py` imports temporary publisher HTML snapshots; no book PDFs or credentials are committed or packaged. It checks full Balbharati pagination and all three OBE curriculum references. Review upstream layout changes before importing again.

## Confirmed pending work

All 46 choices retain explicit pending verification; this is source access, not a claim that every school syllabus is fully imported.

1. Verify each school's chosen subject set, medium, prescribed edition and exam year; obtain current chapter outlines. Catalogue publication year does not prove examination coverage.
2. SCERT's reviewed directory lists 2025–26. Its direct pages, EBooks page and DisplayImage endpoint returned HTTP 502 during this review. Classes 1–9 have no imported direct book mapping; Class 10's three older reference outlines remain archived, not 2026–27 exam syllabus.
3. NIOS Level B English EVS has no linked PDF in the publisher's table; OBE language books and child-track versus adult-track curricula need accredited-agency confirmation. The OBE reference curriculum documents include adult-track wording, explicitly labelled in the app.
4. The NIOS Senior Secondary table links to `seccurriculum.pdf`; its applicability is ambiguous. Do not promote it to a verified Senior Secondary syllabus without examining the document.
5. Primary/middle CBSE annual examination syllabus and Maharashtra examination PDFs still need school/board confirmation. Direct publisher PDF availability is not exhaustively checked.
6. Indexed textbook retrieval, grounded teaching, chapter quizzes and progression remain unavailable. A book link is not enough to enable AI tutoring. Existing Class 3 foundation maths and Class 5 fractions practice remain available with server grading.
7. Telangana Intermediate Classes 11–12 belong to a separate board; they are not labelled SCERT/SSC coverage.

## Fixes and verification

Removed guessed chapter-PDF mapping and stale official/supplemental constants. Missing or changed book selections clear the old PDF URL, iframe and controls. Page explanation/quiz controls stay disabled until grounded retrieval exists. Links require HTTPS, an exact official hostname, no credentials, and HTML escaping. Old board/class responses cannot replace the new selection. No new database schema or account changes are required.

Local: 16 Node regressions, 5 source-registry checks, package separation check and 6 deployment-helper checks. CI additionally runs PHP lint/regressions, all 46 public API choices and invalid selectors, MySQL/auth/security tests and mobile Chromium flows for all four boards, searchable current NCERT books, Balbharati reader, stale reader cleanup, NIOS equivalents, both practice units and parent reporting. CI/deployment evidence is recorded in the PR after completion.
