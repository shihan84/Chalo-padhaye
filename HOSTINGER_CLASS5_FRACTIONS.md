# Class 5 fractions admission practice

This release extends the Hostinger learning foundation with an independently authored fractions unit. It does not claim to be an official Telangana, SSC, CBSE or NIOS textbook chapter.

## Learner flow

The practice selector offers Class 3 addition/subtraction and Class 5 fractions. Grade 5 and above default to fractions; younger profiles default to Class 3. Either unit can be chosen for revision. Today's Plan opens the grade-appropriate unit.

Each ten-question run covers five skills twice: recognising shaded fractions, equivalent fractions, comparing fractions, and adding/subtracting fractions with matching denominators. Worked examples, equal-width shaded bars and spoken question descriptions support learning. The first incorrect answer receives a hint without advancing. After a second incorrect answer, feedback explains the correct result. Next advances only after resolution.

Numeric fraction answers use exact rational equality: `1/2` and `2/4` are both accepted. Whole numbers can represent zero or complete wholes. Decimal, negative and zero-denominator inputs are rejected without consuming an attempt. Comparison questions offer less than, equal to and greater than buttons. Common mistakes such as adding denominators are rejected.

Every new run avoids the last 100 question signatures for that unit. Scores, independent answers, per-skill totals and suggested skills for further practice are computed from server-owned questions. Identical network retries reuse their request identifier, preventing duplicate grading. These exercises measure demonstrated practice, not invigilated exam performance.

## Compatibility and storage

The original Class 3 API default and private state key are retained, so existing saved progress is readable. Fractions use a separate per-child key. No database migration, credential change, branch merge or main/Vercel modification is needed. Existing family ownership checks apply to both units. Release packages include the new private PHP generator; runtime records remain outside the web root and are not overwritten by releases.

## Required verification

- PHP properties: arithmetic correctness, exact equivalent fractions, comparison, invalid denominator rejection, visual bounds, recent-question exclusion and unit isolation.
- MySQL/API: all ten fraction questions, hint-first behavior, request replay, five balanced skill totals, saved resume, fresh retry, grade-appropriate plans and cross-family denial; Class 3 and prior security regressions retained.
- Mobile Chromium: both complete units, fraction and whole-number entry, comparison choices, invalid denominator, reload, switching/resume, fresh retry, voice fallback and parent feedback. Screenshots attach to the CI run.
- Production: pinned SSH deployment, readiness/health, versioned frontend asset equality and unauthenticated protected routes. Production family accounts are not inspected or modified during validation.

Actual Fish Audio playback and microphone recognition still need a supported device and a signed-in family account. This release adds fractions voice descriptions but does not independently verify the production audio provider.

Next recommended release: independently authored English comprehension and EVS activities, followed by adaptive revision. Official board tutoring still requires verified textbook retrieval.
