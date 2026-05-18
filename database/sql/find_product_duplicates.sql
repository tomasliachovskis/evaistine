WITH p AS (
    SELECT
        id,
        name,
        category_id,
        TRIM(SUBSTRING_INDEX(name, ',', -1)) AS suffix,
        TRIM(SUBSTRING_INDEX(name, ',', 1)) AS base,
        (
            CHAR_LENGTH(TRIM(SUBSTRING_INDEX(name, ',', 1)))
            - CHAR_LENGTH(REPLACE(TRIM(SUBSTRING_INDEX(name, ',', 1)), ' ', ''))
            + 1
        ) AS wc
    FROM products
    WHERE (CHAR_LENGTH(name) - CHAR_LENGTH(REPLACE(name, ',', ''))) = 1
      AND TRIM(SUBSTRING_INDEX(name, ',', 1)) NOT REGEXP '(^|[[:space:]])[^[:space:]]*[0-9][^[:space:]]*'
),
w AS (
    SELECT
        id,
        name,
        category_id,
        suffix,
        base,
        wc,
        LOWER(TRIM(SUBSTRING_INDEX(base, ' ', 1))) AS w1,
        IF(wc >= 2, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 2), ' ', -1))), NULL) AS w2,
        IF(wc >= 3, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 3), ' ', -1))), NULL) AS w3,
        IF(wc >= 4, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 4), ' ', -1))), NULL) AS w4,
        IF(wc >= 5, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 5), ' ', -1))), NULL) AS w5,
        IF(wc >= 6, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 6), ' ', -1))), NULL) AS w6,
        IF(wc >= 7, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 7), ' ', -1))), NULL) AS w7,
        IF(wc >= 8, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 8), ' ', -1))), NULL) AS w8,
        IF(wc >= 9, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 9), ' ', -1))), NULL) AS w9,
        IF(wc >= 10, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base, ' ', 10), ' ', -1))), NULL) AS w10
    FROM p
    WHERE wc BETWEEN 1 AND 10
),
tokens AS (
    SELECT id, name, category_id, suffix, base, wc, w1 AS word FROM w WHERE w1 IS NOT NULL AND w1 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w2 FROM w WHERE wc >= 2 AND w2 IS NOT NULL AND w2 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w3 FROM w WHERE wc >= 3 AND w3 IS NOT NULL AND w3 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w4 FROM w WHERE wc >= 4 AND w4 IS NOT NULL AND w4 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w5 FROM w WHERE wc >= 5 AND w5 IS NOT NULL AND w5 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w6 FROM w WHERE wc >= 6 AND w6 IS NOT NULL AND w6 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w7 FROM w WHERE wc >= 7 AND w7 IS NOT NULL AND w7 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w8 FROM w WHERE wc >= 8 AND w8 IS NOT NULL AND w8 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w9 FROM w WHERE wc >= 9 AND w9 IS NOT NULL AND w9 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, wc, w10 FROM w WHERE wc >= 10 AND w10 IS NOT NULL AND w10 <> ''
),
ranked AS (
    SELECT
        id,
        name,
        category_id,
        suffix,
        base,
        wc,
        word,
        ROW_NUMBER() OVER (PARTITION BY id ORDER BY word) AS rn
    FROM tokens
),
s AS (
    SELECT
        w.id,
        w.name,
        w.category_id,
        w.suffix,
        w.base,
        w.wc,
        MAX(CASE WHEN r.rn = 1 THEN r.word END) AS s1,
        MAX(CASE WHEN r.rn = 2 THEN r.word END) AS s2,
        MAX(CASE WHEN r.rn = 3 THEN r.word END) AS s3,
        MAX(CASE WHEN r.rn = 4 THEN r.word END) AS s4,
        MAX(CASE WHEN r.rn = 5 THEN r.word END) AS s5,
        MAX(CASE WHEN r.rn = 6 THEN r.word END) AS s6,
        MAX(CASE WHEN r.rn = 7 THEN r.word END) AS s7,
        MAX(CASE WHEN r.rn = 8 THEN r.word END) AS s8,
        MAX(CASE WHEN r.rn = 9 THEN r.word END) AS s9,
        MAX(CASE WHEN r.rn = 10 THEN r.word END) AS s10
    FROM w
    LEFT JOIN ranked r ON r.id = w.id
    GROUP BY w.id, w.name, w.category_id, w.suffix, w.base, w.wc
)
SELECT
    a.id AS id1,
    b.id AS id2,
    a.name AS name1,
    b.name AS name2,
    a.category_id,
    a.suffix,
    a.wc AS word_count,
    a.base AS base1,
    b.base AS base2
FROM s a
JOIN s b
    ON a.id < b.id
    AND a.category_id = b.category_id
    AND a.suffix = b.suffix
    AND a.wc = b.wc
    AND LOWER(a.base) <> LOWER(b.base)
    AND ((a.wc < 1) OR (
        (CHAR_LENGTH(a.s1) <= 3 AND a.s1 = b.s1)
        OR (CHAR_LENGTH(a.s1) > 3 AND ABS(CHAR_LENGTH(a.s1) - CHAR_LENGTH(b.s1)) <= 3
            AND LEFT(a.s1, LEAST(CHAR_LENGTH(a.s1), CHAR_LENGTH(b.s1)) - 1)
              = LEFT(b.s1, LEAST(CHAR_LENGTH(a.s1), CHAR_LENGTH(b.s1)) - 1))
    ))
    AND ((a.wc < 2) OR (
        (CHAR_LENGTH(a.s2) <= 3 AND a.s2 = b.s2)
        OR (CHAR_LENGTH(a.s2) > 3 AND ABS(CHAR_LENGTH(a.s2) - CHAR_LENGTH(b.s2)) <= 3
            AND LEFT(a.s2, LEAST(CHAR_LENGTH(a.s2), CHAR_LENGTH(b.s2)) - 1)
              = LEFT(b.s2, LEAST(CHAR_LENGTH(a.s2), CHAR_LENGTH(b.s2)) - 1))
    ))
    AND ((a.wc < 3) OR (
        (CHAR_LENGTH(a.s3) <= 3 AND a.s3 = b.s3)
        OR (CHAR_LENGTH(a.s3) > 3 AND ABS(CHAR_LENGTH(a.s3) - CHAR_LENGTH(b.s3)) <= 3
            AND LEFT(a.s3, LEAST(CHAR_LENGTH(a.s3), CHAR_LENGTH(b.s3)) - 1)
              = LEFT(b.s3, LEAST(CHAR_LENGTH(a.s3), CHAR_LENGTH(b.s3)) - 1))
    ))
    AND ((a.wc < 4) OR (
        (CHAR_LENGTH(a.s4) <= 3 AND a.s4 = b.s4)
        OR (CHAR_LENGTH(a.s4) > 3 AND ABS(CHAR_LENGTH(a.s4) - CHAR_LENGTH(b.s4)) <= 3
            AND LEFT(a.s4, LEAST(CHAR_LENGTH(a.s4), CHAR_LENGTH(b.s4)) - 1)
              = LEFT(b.s4, LEAST(CHAR_LENGTH(a.s4), CHAR_LENGTH(b.s4)) - 1))
    ))
    AND ((a.wc < 5) OR (
        (CHAR_LENGTH(a.s5) <= 3 AND a.s5 = b.s5)
        OR (CHAR_LENGTH(a.s5) > 3 AND ABS(CHAR_LENGTH(a.s5) - CHAR_LENGTH(b.s5)) <= 3
            AND LEFT(a.s5, LEAST(CHAR_LENGTH(a.s5), CHAR_LENGTH(b.s5)) - 1)
              = LEFT(b.s5, LEAST(CHAR_LENGTH(a.s5), CHAR_LENGTH(b.s5)) - 1))
    ))
    AND ((a.wc < 6) OR (
        (CHAR_LENGTH(a.s6) <= 3 AND a.s6 = b.s6)
        OR (CHAR_LENGTH(a.s6) > 3 AND ABS(CHAR_LENGTH(a.s6) - CHAR_LENGTH(b.s6)) <= 3
            AND LEFT(a.s6, LEAST(CHAR_LENGTH(a.s6), CHAR_LENGTH(b.s6)) - 1)
              = LEFT(b.s6, LEAST(CHAR_LENGTH(a.s6), CHAR_LENGTH(b.s6)) - 1))
    ))
    AND ((a.wc < 7) OR (
        (CHAR_LENGTH(a.s7) <= 3 AND a.s7 = b.s7)
        OR (CHAR_LENGTH(a.s7) > 3 AND ABS(CHAR_LENGTH(a.s7) - CHAR_LENGTH(b.s7)) <= 3
            AND LEFT(a.s7, LEAST(CHAR_LENGTH(a.s7), CHAR_LENGTH(b.s7)) - 1)
              = LEFT(b.s7, LEAST(CHAR_LENGTH(a.s7), CHAR_LENGTH(b.s7)) - 1))
    ))
    AND ((a.wc < 8) OR (
        (CHAR_LENGTH(a.s8) <= 3 AND a.s8 = b.s8)
        OR (CHAR_LENGTH(a.s8) > 3 AND ABS(CHAR_LENGTH(a.s8) - CHAR_LENGTH(b.s8)) <= 3
            AND LEFT(a.s8, LEAST(CHAR_LENGTH(a.s8), CHAR_LENGTH(b.s8)) - 1)
              = LEFT(b.s8, LEAST(CHAR_LENGTH(a.s8), CHAR_LENGTH(b.s8)) - 1))
    ))
    AND ((a.wc < 9) OR (
        (CHAR_LENGTH(a.s9) <= 3 AND a.s9 = b.s9)
        OR (CHAR_LENGTH(a.s9) > 3 AND ABS(CHAR_LENGTH(a.s9) - CHAR_LENGTH(b.s9)) <= 3
            AND LEFT(a.s9, LEAST(CHAR_LENGTH(a.s9), CHAR_LENGTH(b.s9)) - 1)
              = LEFT(b.s9, LEAST(CHAR_LENGTH(a.s9), CHAR_LENGTH(b.s9)) - 1))
    ))
    AND ((a.wc < 10) OR (
        (CHAR_LENGTH(a.s10) <= 3 AND a.s10 = b.s10)
        OR (CHAR_LENGTH(a.s10) > 3 AND ABS(CHAR_LENGTH(a.s10) - CHAR_LENGTH(b.s10)) <= 3
            AND LEFT(a.s10, LEAST(CHAR_LENGTH(a.s10), CHAR_LENGTH(b.s10)) - 1)
              = LEFT(b.s10, LEAST(CHAR_LENGTH(a.s10), CHAR_LENGTH(b.s10)) - 1))
    ))
ORDER BY a.category_id, a.suffix, a.wc, a.base
