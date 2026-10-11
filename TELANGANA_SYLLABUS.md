# Telangana syllabus support — Hostinger

The Telangana study mode opens an independent syllabus browser for classes 1–10, defaulting to the selected child's class. Class 10 is labelled SSC. This fixes the previous Maharashtra subtitle shown to Telangana students. Parents can browse another class without changing the child's profile.

## Coverage and provenance

`data/telangana_syllabus.json` stores public curriculum metadata; `/api/syllabus?curriculum=telangana&grade=10` exposes a single class. The data and PHP implementation are packaged privately; no PDF or credentials are committed or bundled. Other curriculum APIs remain compatible.

Sources reviewed 11 October 2026:

- [SCERT textbook directory](https://www.scert.telangana.gov.in/DisplayContent.aspx?encry=ammkNW4%2Fgx%2BNeApstGPX%2BA%3D%3D): indexed page explicitly labelled 2025–26; classes I–X and English-medium subject groupings. Language choices depend on the school's first/second language scheme.
- [SCERT syllabus directory](https://www.scert.telangana.gov.in/Displaycontent.aspx?encry=Z%2B4YBPX%2BcaUE5uiQSu6xdg%3D%3D): official subject syllabus references.
- [Class X Mathematics](https://scert.telangana.gov.in/PDF/publication/ebooks/10EM_MAT.pdf): indexed table of contents, 14 chapters. Archive edition cannot be confirmed; explicitly labelled accordingly.
- [Class X English, 2024–25](https://scert.telangana.gov.in/pdf/publication/ebooks/10%20english%202024-25.pdf): indexed table of contents, eight thematic units.
- [Class X Social Studies, 2021–22](https://scert.telangana.gov.in/pdf/publication/ebooks2019/10%20social%20em-21.pdf): indexed table of contents, 21 chapters, ending with Telangana state formation.

Direct SCERT page/PDF fetches timed out or returned gateway errors during review. Reference outlines were checked against indexed content from the official publisher, not treated as fresh live downloads. No source is represented as a verified 2026–27 exam syllabus. Use the SCERT directory and school confirmation for current editions, exam exclusions, assessment pattern and language selections. Science, language and classes 1–9 chapter outlines remain explicitly pending verification. These omissions are visible in the UI.

## Tutor readiness

Syllabus browsing is separate from lesson progress. Archived reference outlines are deliberately not imported into mastery-gated lesson definitions. Catalog availability remains false and tutoring fails closed until trusted retrieval is implemented and the relevant current books are verified. No database migration is needed.

## Validation

Browser tests cover Telangana mode labels, syllabus display while tutoring is unavailable, escaped chapter titles, allowed official link hosts and rejection of unsafe URL schemes/credentials, and stale responses after a class change. PHP helper checks cover grade boundaries, edition disclosure and unchanged tutor availability. HTTP integration checks exercise the public SSC endpoint and malformed/cross-board queries against the isolated MySQL fixture. Packaging checks require the metadata in private storage and forbid textbooks/secrets in the archive.

Deployment uses the existing Hostinger release workflow after PHP/MySQL CI passes. The main and hostinger-php branches and Vercel deployment are not changed or merged.
