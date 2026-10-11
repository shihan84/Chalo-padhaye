# Hostinger syllabus and textbook audit — 11 October 2026

Scope: all 46 board/class choices in the deployed Hostinger library, not Vercel. Baseline commit `bf2dc483eb5c8d30956eefbbbf724df96978e292`. All 46 live API choices matched the baseline registry, outlines, pending notices and book mappings. Two initial live requests timed out and passed on retry. No production accounts, paid AI/audio calls or database writes were used.

## Result

The library is usable for source browsing but is **not a complete verified syllabus/textbook collection**. All 1,090 mapped NCERT selectors match a fresh official catalogue. All 297 CBSE syllabus entries match the current 2026–27 publisher page (68 for IX, 65 for X, 82 shared XI–XII repeated across two class choices). This confirms publisher listings, not every PDF edition or every school-prescribed subject.

There are 1,601 unique registry URLs. Instead of requesting 1,090 identical dynamic NCERT page shells, every selector was compared with the fresh class/subject/title/URL catalogue; a representative selector for each class was separately probed. The remaining URLs and representative selectors total 523 network checks. Range requests read at most 4 KiB per response. Failed requests were retried where practical; network errors and gateway/server failures remain inconclusive. No URL or chapter filename was invented.

| Check outcome | Unique URLs |
|---|---:|
| HTML returned (catalogue, not a verified PDF) | 93 |
| HTTP error (see receipts; 404s versus temporary errors distinguished) | 15 |
| PDF signature returned (full book/edition not verified) | 415 |

All 239 mapped Balbharati book URLs returned PDF signatures. NIOS’s eight mapped unique OBE textbook URLs also returned PDF signatures, but its Indian Culture and Heritage (223) subject catalogue returned HTTP 500. The generic NCERT directory and one Class XI selector returned HTTP 502; their metadata is present in the freshly fetched catalogue. These errors do not establish that the books are missing.

Full public URL receipts, per-class uses and repeated 404 evidence are in `audits/hostinger-textbook-links-2026-10-11.json`. Snapshot SHA-256 values record the freshly fetched NCERT, CBSE and NIOS catalogues; full publisher prose/PDFs are not committed.

## Confirmed issues corrected

Seven unique CBSE syllabus URLs returned HTTP 404 in both the range check and a separate plain GET. These create 12 affected syllabus entries because XI–XII documents are shared. The registry records the failure/date; the interface retains the subject with an unavailable-download notice instead of an active broken download link. The official curriculum directory remains available. No syllabus was silently removed or replaced with another class/medium.

| Class | Missing publisher download |
|---|---|
| IX | Interdisciplinary Areas; Computational Thinking & AI |
| XI–XII | Hindi Geography; History; Political Science; Physical Education; Knowledge Tradition–Practices of India |

NCERT has two Class 7 publisher-label collisions: `gsikr1=0-20` is listed as both Sindhi and Telugu Kriti-I; `gmres2=0-8` as both Marathi and Telugu Exploring Society Part-II. Existing first-listed mappings remain; Class 7 now warns users to confirm the language inside the book. Choosing a different filename from its naming pattern would be an unverified replacement.

The older coverage report incorrectly said no indexed page lessons existed. It now points to the verified 12-PDF/191-chapter page index and distinguishes reading checks from pending chapter exams/progression.

## Every class reviewed

Book counts include translated/district editions and repeated NIOS equivalents. Syllabus counts include shared documents and unavailable publisher links; they do not describe a school’s chosen subjects. PDF-signature counts apply only to directly mapped book URLs; an accessible NCERT/NIOS subject catalogue is not counted as a PDF. Indexed entries repeat shared NIOS books across equivalent classes.

| Board | Class / equivalent | Mapped books | Syllabus entries | Book PDF signatures | Indexed book entries |
|---|---|---:|---:|---:|---:|
| CBSE / NCERT | Class 1 | 26 | 0 | 0 | 0 |
| CBSE / NCERT | Class 2 | 26 | 0 | 0 | 0 |
| CBSE / NCERT | Class 3 | 95 | 0 | 0 | 0 |
| CBSE / NCERT | Class 4 | 94 | 0 | 0 | 0 |
| CBSE / NCERT | Class 5 | 93 | 0 | 0 | 0 |
| CBSE / NCERT | Class 6 | 140 | 0 | 0 | 0 |
| CBSE / NCERT | Class 7 | 182 | 0 | 0 | 0 |
| CBSE / NCERT | Class 8 | 164 | 0 | 0 | 0 |
| CBSE / NCERT | Class 9 | 26 | 68 | 0 | 0 |
| CBSE / NCERT | Class 10 | 34 | 65 | 0 | 0 |
| CBSE / NCERT | Class 11 | 107 | 82 | 0 | 0 |
| CBSE / NCERT | Class 12 | 103 | 82 | 0 | 0 |
| Maharashtra / Balbharati | Class 1 | 3 | 0 | 3 | 0 |
| Maharashtra / Balbharati | Class 2 | 3 | 0 | 3 | 0 |
| Maharashtra / Balbharati | Class 3 | 42 | 0 | 42 | 3 |
| Maharashtra / Balbharati | Class 4 | 8 | 0 | 8 | 0 |
| Maharashtra / Balbharati | Class 5 | 14 | 0 | 14 | 4 |
| Maharashtra / Balbharati | Class 6 | 9 | 0 | 9 | 0 |
| Maharashtra / Balbharati | Class 7 | 14 | 0 | 14 | 0 |
| Maharashtra / Balbharati | Class 8 | 20 | 0 | 20 | 0 |
| Maharashtra / Balbharati | Class 9 | 25 | 0 | 25 | 0 |
| Maharashtra / Balbharati | Class 10 | 23 | 0 | 23 | 0 |
| Maharashtra / Balbharati | Class 11 | 39 | 0 | 39 | 0 |
| Maharashtra / Balbharati | Class 12 | 39 | 0 | 39 | 0 |
| Telangana / SCERT | Telangana State Board Class 1 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 2 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 3 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 4 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 5 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 6 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 7 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 8 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana State Board Class 9 | 0 | 0 | 0 | 0 |
| Telangana / SCERT | Telangana SSC Class 10 | 3 | 0 | 0 | 0 |
| NIOS | OBE Level A · equivalent classes 1–3 | 3 | 1 | 3 | 3 |
| NIOS | OBE Level A · equivalent classes 1–3 | 3 | 1 | 3 | 3 |
| NIOS | OBE Level A · equivalent classes 1–3 | 3 | 1 | 3 | 3 |
| NIOS | OBE Level B · equivalent classes 4–5 | 2 | 1 | 2 | 2 |
| NIOS | OBE Level B · equivalent classes 4–5 | 2 | 1 | 2 | 2 |
| NIOS | OBE Level C · equivalent classes 6–8 | 3 | 1 | 3 | 0 |
| NIOS | OBE Level C · equivalent classes 6–8 | 3 | 1 | 3 | 0 |
| NIOS | OBE Level C · equivalent classes 6–8 | 3 | 1 | 3 | 0 |
| NIOS | NIOS Secondary · Class 10 equivalent | 35 | 1 | 0 | 0 |
| NIOS | NIOS Secondary · Class 10 equivalent | 35 | 1 | 0 | 0 |
| NIOS | NIOS Senior Secondary · Class 12 equivalent | 41 | 0 | 0 | 0 |
| NIOS | NIOS Senior Secondary · Class 12 equivalent | 41 | 0 | 0 | 0 |

## Remaining work, in priority order

1. **Telangana:** Classes 1–9 have no imported direct book mappings; Class 10 has three archived references, not a current complete SSC set. Official directory/PDF access returned gateway errors/timeouts. Search-indexed official 2026–27 PDFs were discovered for Classes 3/4/5/7/9/10, but fresh requests could not retrieve their bytes: record as candidates, not verified books. Obtain/retrieve the official current directory and verify class, subject, medium, cover/edition and contents before importing. Separate Intermediate XI–XII is outside SCERT/SSC coverage.
2. **Current exam syllabi:** Maharashtra has 239 publisher PDF entries, but separate current exam syllabi/prescribed subject choices remain pending. CBSE primary/middle uses school-prescribed NCERT materials; no separate annual CBSE exam syllabus is imported for I–VIII. Repair/recheck the seven CBSE publisher 404s when updated.
3. **NIOS:** English Level B EVS has an image icon without an href in the current official table, so there is no verified download to import. OBE resources/curricula have adult-track wording; confirm child-track suitability and language/agency requirements. The Senior Secondary table’s `seccurriculum.pdf` remains ambiguous and is not promoted to a verified senior syllabus. Secondary 2020 curriculum is archived.
4. **Edition/content verification:** 12 unique PDFs have full pinned hashes, reviewed covers/contents and private page indexes (1,515 physical pages / 191 chapter entries). Remaining books need complete edition, contents/page-offset, medium and school-prescription checks. Maharashtra district variants must match the child’s school/district; a 2026 catalogue filter is not proof every PDF was printed in 2026.
5. **Teaching and tests:** Page lessons are ready for Maharashtra III/V and NIOS I–V equivalents only, for their indexed books. Image-only pages still need extraction/OCR validation. Add chapter exams/progression only after trusted chapter mappings and server grading exist. Catalogue/signature checks do not test every page explanation, paid provider output, PDF iframe rendering or every language variant.

## Validation and sources

Local checks: 18 Node frontend regressions (including unavailable-document rendering and escaping), 5 curriculum checks, 4 private-corpus checks, package separation and 6 deployment checks passed. PHP/MySQL/mobile integration results and deployment evidence are recorded in the pull request after CI.

Primary sources:

- NCERT: https://www.ncert.nic.in/textbook.php?eesa1=0-10 (fresh complete selector catalogue embedded in HTML; generic `?ln=en` remained intermittent)
- CBSE 2026–27: https://cbseacademic.nic.in/curriculum_2027.html
- Balbharati: https://books.ebalbharati.in/ and https://ebooks.ebalbharati.in/
- Telangana: https://www.scert.telangana.gov.in/
- NIOS OBE: https://rcpune.nios.ac.in/open-basic-education-courses-material.html
- NIOS curriculum: https://rcpatna.nios.ac.in/open-basic-educationobe.html

Only Hostinger source labels, pending notices, frontend rendering, audit evidence and CI base coverage change. Main, Vercel and the original hostinger-php branch are not modified or merged.
