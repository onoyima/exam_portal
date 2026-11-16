-- Remove duplicate assessment-criteria questions that init_questions.php
-- inserted without an exam_id (exam_id IS NULL). One copy of each text is kept.
-- Run only after taking a backup (scripts/cleanup-questions.sh does that).

DELETE q
FROM questions q
JOIN (
    SELECT text, MIN(id) AS keep_id
    FROM questions
    WHERE exam_id IS NULL
    GROUP BY text
    HAVING COUNT(*) > 1
) d ON q.text = d.text
WHERE q.exam_id IS NULL
  AND q.id <> d.keep_id;
