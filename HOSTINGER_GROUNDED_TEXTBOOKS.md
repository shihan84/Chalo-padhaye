# Verified textbook pages and remaining coverage

Hostinger change only, based on the curriculum-library release. Main and the Vercel application are unchanged. No branch merges or production database migrations.

## Delivered

Twelve hash-pinned publisher PDFs have complete contents lists and private page text: Maharashtra Class 3 English, Mathematics and The World Around Us; Maharashtra Class 5 English, Mathematics and both Environmental Studies parts; NIOS Level A Mathematics, Environmental Studies and Computer Skills; NIOS Level B Mathematics and Computer Skills. The checked NIOS covers explicitly state Open Basic Education (Adult), copyright 2016. Individual book labels make the adult track and parent/agency suitability confirmation visible. These are reading resources, not verified child-track curriculum. The NIOS books are shared across equivalent classes, rather than counted as distinct editions.

The Maharashtra Class 3 PDFs state first edition 2026 and approval for 2026–27. The older Maharashtra Class 5 copies remain labelled publisher-listed copies requiring school edition confirmation. A catalogue-year filter is not proof of the PDF reprint year. NIOS hosting dates are not proof of a current child-track syllabus.

The manifest records each publisher URL, complete SHA256, physical PDF page count, contents entries, printed page and physical PDF page. Twelve unique outlines produce twenty class-specific outline entries, alongside the three existing Telangana archives. The physical-page offsets were checked against the opening lessons; front matter and blank pages are included in numbering.

PDFs are downloaded only by the release builder, bounded at 64 MiB, without redirects, and accepted only if their bytes match the reviewed manifest. Poppler extracts page text; its output and the PDFs are never committed. Generated text indexes are included only under `chalo-private/data/textbook-corpus/`, outside public_html. Deployment fails before SSH uploads if a publisher changes a pinned book or extraction becomes unusable. Existing files retain the normal rollback backup.

Authenticated children can open a compatible book, choose a chapter/PDF page, request a short AI explanation or a one-word reading check, and read the response aloud. Each response cites the selected publisher page. The Groq prompt includes only that page and the child's class; it receives no child name, credentials, family records or arbitrary client prompt. Provider failures or a missing key fall back to the reading check. AI prose is generated, not a guarantee of correctness.

The reading question and exact expected word are generated and stored on the server. The first wrong answer gives a hint, the next explains the word. One active reading-check file per child bounds storage across retries; new checks replace earlier checks. Unknown session tokens cannot create files. Checks are scoped to the authenticated child, book, page and thirty-minute session. They cannot write subject mastery or chapter test scores. Client assessments remain rejected. Existing arithmetic/fractions practice remains the server-graded mastery path. Unmapped structured chat continues to fail closed.

Family limits: 100 page turns/day, 6/minute, 30 AI explanations/day; input/page bounds and private-state storage cap apply. Browsers cannot submit URLs for retrieval. Switching child, board or PDF page clears the prior page session and ignores delayed responses. Library availability reflects a matching deployed index, not a PDF link alone.

NIOS labels now correctly cover A (classes 1–3), B (4–5), C (6–8), Secondary (9–10), and Senior Secondary (11–12).

## Pending external/source work

- Telangana current-edition directory and Class 4 2026–27 English PDF returned HTTP 502 during this review. Indexed search results alone cannot verify PDF bytes or chapters. No new Telangana content is labelled current or teaching-ready.
- NCERT selected-book download page also returned HTTP 502. The existing official catalogue remains accessible through the library, but no guessed chapter filenames are added.
- NIOS Level B English EVS has no linked PDF in the reviewed publisher table. Senior Secondary curriculum link points to an ambiguous secondary-named PDF. Child-track curriculum, language texts and agency choices remain unverified.
- Remaining classes, translations, district-specific Class 3 books, and school-prescribed subject/edition choices still need individual verification. Twelve indexed books do not mean all 46 class choices are teaching-ready.
- Broad subject mastery, structured lesson sequencing, and chapter exams require authoritative grading content; reading recall and LLM judgement do not substitute for them.

## Validation

Node frontend tests; Python source, corpus, package and deployment tests; PHP lint/regressions; isolated MySQL API and mobile-browser checks. Fixtures contain synthetic text, use no production children, and make no paid provider calls. The release builder revalidates publisher bytes and private corpus before upload. Live checks verify catalogue/index availability and protected routes without creating accounts or making billable AI requests.
