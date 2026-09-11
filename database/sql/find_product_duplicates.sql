WITH p AS (
    SELECT
        id,
        name,
        category_id,
        TRIM(SUBSTRING_INDEX(name, ',', -1)) AS suffix,
        TRIM(SUBSTRING_INDEX(name, ',', CHAR_LENGTH(name) - CHAR_LENGTH(REPLACE(name, ',', '')))) AS base,
        LOWER(
            TRIM(
                REGEXP_REPLACE(
                    REGEXP_REPLACE(name, '[[:space:]]*,[[:space:]]*', ', '),
                    '[[:space:]]+',
                    ' '
                )
            )
        ) AS normalized_name
    FROM products
    WHERE (CHAR_LENGTH(name) - CHAR_LENGTH(REPLACE(name, ',', ''))) BETWEEN 1 AND 2
),
p_filtered AS (
    SELECT
        id,
        name,
        category_id,
        suffix,
        base,
        TRIM(REGEXP_REPLACE(REPLACE(REPLACE(base, '.', ' '), ',', ' '), '[[:space:]]+', ' ')) AS base_norm,
        (
            CHAR_LENGTH(TRIM(REGEXP_REPLACE(REPLACE(REPLACE(base, '.', ' '), ',', ' '), '[[:space:]]+', ' ')))
            - CHAR_LENGTH(REPLACE(TRIM(REGEXP_REPLACE(REPLACE(REPLACE(base, '.', ' '), ',', ' '), '[[:space:]]+', ' ')), ' ', ''))
            + 1
        ) AS wc
    FROM p
    WHERE base NOT REGEXP '(^|[[:space:]])[^[:space:]]*[0-9][^[:space:]]*'
),
p_with_size AS (
    SELECT
        pf.*,
        TRIM(SUBSTRING_INDEX(TRIM(pf.suffix), ' ', 1)) AS size_num_raw,
        LOWER(TRIM(SUBSTRING_INDEX(TRIM(pf.suffix), ' ', -1))) AS size_unit
    FROM p_filtered pf
),
p_sized AS (
    SELECT
        p.*,
        CASE
            WHEN TRIM(p.suffix) REGEXP '[0-9][[:alnum:].,[:space:]]*[-–—][[:alnum:].,[:space:]]*[0-9]' THEN 1
            WHEN TRIM(p.suffix) REGEXP '[0-9][[:space:]]*[x×][[:space:]]*[0-9]' THEN 1
            ELSE 0
        END AS suffix_has_range,
        CASE
            WHEN p.size_unit IN ('ml', 'l') THEN 'volume'
            WHEN p.size_unit IN ('g', 'kg') THEN 'mass'
            WHEN p.size_unit IN ('vnt', 'vnt.') THEN 'count'
            ELSE NULL
        END AS suffix_kind,
        CASE
            WHEN TRIM(p.suffix) REGEXP '[0-9][[:alnum:].,[:space:]]*[-–—][[:alnum:].,[:space:]]*[0-9]' THEN NULL
            WHEN TRIM(p.suffix) REGEXP '[0-9][[:space:]]*[x×][[:space:]]*[0-9]' THEN NULL
            WHEN p.size_unit = 'ml' AND p.size_num_raw REGEXP '^[0-9]'
                THEN CAST(REPLACE(p.size_num_raw, ',', '.') AS DECIMAL(12, 4))
            WHEN p.size_unit = 'l' AND p.size_num_raw REGEXP '^[0-9]'
                THEN CAST(REPLACE(p.size_num_raw, ',', '.') AS DECIMAL(12, 4)) * 1000
            WHEN p.size_unit = 'g' AND p.size_num_raw REGEXP '^[0-9]'
                THEN CAST(REPLACE(p.size_num_raw, ',', '.') AS DECIMAL(12, 4))
            WHEN p.size_unit = 'kg' AND p.size_num_raw REGEXP '^[0-9]'
                THEN CAST(REPLACE(p.size_num_raw, ',', '.') AS DECIMAL(12, 4)) * 1000
            WHEN p.size_unit IN ('vnt', 'vnt.') AND p.size_num_raw REGEXP '^[0-9]'
                THEN CAST(REPLACE(p.size_num_raw, ',', '.') AS DECIMAL(12, 4))
            ELSE NULL
        END AS suffix_norm
    FROM p_with_size p
),
w AS (
    SELECT
        id,
        name,
        category_id,
        suffix,
        base,
        suffix_kind,
        suffix_norm,
        wc,
        LOWER(TRIM(SUBSTRING_INDEX(base_norm, ' ', 1))) AS w1,
        IF(wc >= 2, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 2), ' ', -1))), NULL) AS w2,
        IF(wc >= 3, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 3), ' ', -1))), NULL) AS w3,
        IF(wc >= 4, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 4), ' ', -1))), NULL) AS w4,
        IF(wc >= 5, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 5), ' ', -1))), NULL) AS w5,
        IF(wc >= 6, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 6), ' ', -1))), NULL) AS w6,
        IF(wc >= 7, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 7), ' ', -1))), NULL) AS w7,
        IF(wc >= 8, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 8), ' ', -1))), NULL) AS w8,
        IF(wc >= 9, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 9), ' ', -1))), NULL) AS w9,
        IF(wc >= 10, LOWER(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(base_norm, ' ', 10), ' ', -1))), NULL) AS w10
    FROM p_sized
    WHERE wc BETWEEN 1 AND 10
),
tokens AS (
    SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w1 AS word FROM w WHERE w1 IS NOT NULL AND w1 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w2 FROM w WHERE wc >= 2 AND w2 IS NOT NULL AND w2 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w3 FROM w WHERE wc >= 3 AND w3 IS NOT NULL AND w3 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w4 FROM w WHERE wc >= 4 AND w4 IS NOT NULL AND w4 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w5 FROM w WHERE wc >= 5 AND w5 IS NOT NULL AND w5 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w6 FROM w WHERE wc >= 6 AND w6 IS NOT NULL AND w6 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w7 FROM w WHERE wc >= 7 AND w7 IS NOT NULL AND w7 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w8 FROM w WHERE wc >= 8 AND w8 IS NOT NULL AND w8 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w9 FROM w WHERE wc >= 9 AND w9 IS NOT NULL AND w9 <> ''
    UNION ALL SELECT id, name, category_id, suffix, base, suffix_kind, suffix_norm, wc, w10 FROM w WHERE wc >= 10 AND w10 IS NOT NULL AND w10 <> ''
),
ranked AS (
    SELECT
        id,
        name,
        category_id,
        suffix,
        base,
        suffix_kind,
        suffix_norm,
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
        w.suffix_kind,
        w.suffix_norm,
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
    GROUP BY w.id, w.name, w.category_id, w.suffix, w.base, w.suffix_kind, w.suffix_norm, w.wc
),
fuzzy_pairs AS (
SELECT
    a.id AS id1,
    b.id AS id2,
    a.name AS name1,
    b.name AS name2,
    a.category_id,
    a.suffix AS suffix1,
    b.suffix AS suffix2,
    a.suffix_norm,
    a.wc AS word_count,
    a.base AS base1,
    b.base AS base2
FROM s a
JOIN s b
    ON a.id < b.id
    AND a.category_id = b.category_id
    AND a.wc = b.wc
    AND (
        LOWER(a.base) <> LOWER(b.base)
        OR (
            LOWER(a.base) = LOWER(b.base)
            AND a.suffix <> b.suffix
            AND a.suffix_norm IS NOT NULL
            AND b.suffix_norm IS NOT NULL
            AND a.suffix_kind = b.suffix_kind
            AND a.suffix_norm = b.suffix_norm
        )
    )
    AND (
        (a.suffix_norm IS NOT NULL AND b.suffix_norm IS NOT NULL
            AND a.suffix_kind = b.suffix_kind AND a.suffix_norm = b.suffix_norm)
        OR
        (a.suffix_norm IS NULL AND b.suffix_norm IS NULL AND a.suffix = b.suffix)
    )
    AND ((a.wc < 1) OR (
        (CHAR_LENGTH(a.s1) <= 3 AND a.s1 = b.s1)
        OR (CHAR_LENGTH(a.s1) > 3 AND ABS(CHAR_LENGTH(a.s1) - CHAR_LENGTH(b.s1)) <= 3
            AND LEFT(a.s1, LEAST(CHAR_LENGTH(a.s1), CHAR_LENGTH(b.s1)) - 1)
              = LEFT(b.s1, LEAST(CHAR_LENGTH(a.s1), CHAR_LENGTH(b.s1)) - 1))
        OR (CHAR_LENGTH(a.s1) >= 3 AND CHAR_LENGTH(b.s1) >= 3
            AND (a.s1 LIKE CONCAT(b.s1, '%') OR b.s1 LIKE CONCAT(a.s1, '%')))
        OR ((a.s1 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s1 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s1 LIKE CONCAT(b.s1, '%') OR b.s1 LIKE CONCAT(a.s1, '%')))
    ))
    AND ((a.wc < 2) OR (
        (CHAR_LENGTH(a.s2) <= 3 AND a.s2 = b.s2)
        OR (CHAR_LENGTH(a.s2) > 3 AND ABS(CHAR_LENGTH(a.s2) - CHAR_LENGTH(b.s2)) <= 3
            AND LEFT(a.s2, LEAST(CHAR_LENGTH(a.s2), CHAR_LENGTH(b.s2)) - 1)
              = LEFT(b.s2, LEAST(CHAR_LENGTH(a.s2), CHAR_LENGTH(b.s2)) - 1))
        OR (CHAR_LENGTH(a.s2) >= 3 AND CHAR_LENGTH(b.s2) >= 3
            AND (a.s2 LIKE CONCAT(b.s2, '%') OR b.s2 LIKE CONCAT(a.s2, '%')))
        OR ((a.s2 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s2 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s2 LIKE CONCAT(b.s2, '%') OR b.s2 LIKE CONCAT(a.s2, '%')))
    ))
    AND ((a.wc < 3) OR (
        (CHAR_LENGTH(a.s3) <= 3 AND a.s3 = b.s3)
        OR (CHAR_LENGTH(a.s3) > 3 AND ABS(CHAR_LENGTH(a.s3) - CHAR_LENGTH(b.s3)) <= 3
            AND LEFT(a.s3, LEAST(CHAR_LENGTH(a.s3), CHAR_LENGTH(b.s3)) - 1)
              = LEFT(b.s3, LEAST(CHAR_LENGTH(a.s3), CHAR_LENGTH(b.s3)) - 1))
        OR (CHAR_LENGTH(a.s3) >= 3 AND CHAR_LENGTH(b.s3) >= 3
            AND (a.s3 LIKE CONCAT(b.s3, '%') OR b.s3 LIKE CONCAT(a.s3, '%')))
        OR ((a.s3 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s3 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s3 LIKE CONCAT(b.s3, '%') OR b.s3 LIKE CONCAT(a.s3, '%')))
    ))
    AND ((a.wc < 4) OR (
        (CHAR_LENGTH(a.s4) <= 3 AND a.s4 = b.s4)
        OR (CHAR_LENGTH(a.s4) > 3 AND ABS(CHAR_LENGTH(a.s4) - CHAR_LENGTH(b.s4)) <= 3
            AND LEFT(a.s4, LEAST(CHAR_LENGTH(a.s4), CHAR_LENGTH(b.s4)) - 1)
              = LEFT(b.s4, LEAST(CHAR_LENGTH(a.s4), CHAR_LENGTH(b.s4)) - 1))
        OR (CHAR_LENGTH(a.s4) >= 3 AND CHAR_LENGTH(b.s4) >= 3
            AND (a.s4 LIKE CONCAT(b.s4, '%') OR b.s4 LIKE CONCAT(a.s4, '%')))
        OR ((a.s4 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s4 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s4 LIKE CONCAT(b.s4, '%') OR b.s4 LIKE CONCAT(a.s4, '%')))
    ))
    AND ((a.wc < 5) OR (
        (CHAR_LENGTH(a.s5) <= 3 AND a.s5 = b.s5)
        OR (CHAR_LENGTH(a.s5) > 3 AND ABS(CHAR_LENGTH(a.s5) - CHAR_LENGTH(b.s5)) <= 3
            AND LEFT(a.s5, LEAST(CHAR_LENGTH(a.s5), CHAR_LENGTH(b.s5)) - 1)
              = LEFT(b.s5, LEAST(CHAR_LENGTH(a.s5), CHAR_LENGTH(b.s5)) - 1))
        OR (CHAR_LENGTH(a.s5) >= 3 AND CHAR_LENGTH(b.s5) >= 3
            AND (a.s5 LIKE CONCAT(b.s5, '%') OR b.s5 LIKE CONCAT(a.s5, '%')))
        OR ((a.s5 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s5 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s5 LIKE CONCAT(b.s5, '%') OR b.s5 LIKE CONCAT(a.s5, '%')))
    ))
    AND ((a.wc < 6) OR (
        (CHAR_LENGTH(a.s6) <= 3 AND a.s6 = b.s6)
        OR (CHAR_LENGTH(a.s6) > 3 AND ABS(CHAR_LENGTH(a.s6) - CHAR_LENGTH(b.s6)) <= 3
            AND LEFT(a.s6, LEAST(CHAR_LENGTH(a.s6), CHAR_LENGTH(b.s6)) - 1)
              = LEFT(b.s6, LEAST(CHAR_LENGTH(a.s6), CHAR_LENGTH(b.s6)) - 1))
        OR (CHAR_LENGTH(a.s6) >= 3 AND CHAR_LENGTH(b.s6) >= 3
            AND (a.s6 LIKE CONCAT(b.s6, '%') OR b.s6 LIKE CONCAT(a.s6, '%')))
        OR ((a.s6 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s6 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s6 LIKE CONCAT(b.s6, '%') OR b.s6 LIKE CONCAT(a.s6, '%')))
    ))
    AND ((a.wc < 7) OR (
        (CHAR_LENGTH(a.s7) <= 3 AND a.s7 = b.s7)
        OR (CHAR_LENGTH(a.s7) > 3 AND ABS(CHAR_LENGTH(a.s7) - CHAR_LENGTH(b.s7)) <= 3
            AND LEFT(a.s7, LEAST(CHAR_LENGTH(a.s7), CHAR_LENGTH(b.s7)) - 1)
              = LEFT(b.s7, LEAST(CHAR_LENGTH(a.s7), CHAR_LENGTH(b.s7)) - 1))
        OR (CHAR_LENGTH(a.s7) >= 3 AND CHAR_LENGTH(b.s7) >= 3
            AND (a.s7 LIKE CONCAT(b.s7, '%') OR b.s7 LIKE CONCAT(a.s7, '%')))
        OR ((a.s7 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s7 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s7 LIKE CONCAT(b.s7, '%') OR b.s7 LIKE CONCAT(a.s7, '%')))
    ))
    AND ((a.wc < 8) OR (
        (CHAR_LENGTH(a.s8) <= 3 AND a.s8 = b.s8)
        OR (CHAR_LENGTH(a.s8) > 3 AND ABS(CHAR_LENGTH(a.s8) - CHAR_LENGTH(b.s8)) <= 3
            AND LEFT(a.s8, LEAST(CHAR_LENGTH(a.s8), CHAR_LENGTH(b.s8)) - 1)
              = LEFT(b.s8, LEAST(CHAR_LENGTH(a.s8), CHAR_LENGTH(b.s8)) - 1))
        OR (CHAR_LENGTH(a.s8) >= 3 AND CHAR_LENGTH(b.s8) >= 3
            AND (a.s8 LIKE CONCAT(b.s8, '%') OR b.s8 LIKE CONCAT(a.s8, '%')))
        OR ((a.s8 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s8 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s8 LIKE CONCAT(b.s8, '%') OR b.s8 LIKE CONCAT(a.s8, '%')))
    ))
    AND ((a.wc < 9) OR (
        (CHAR_LENGTH(a.s9) <= 3 AND a.s9 = b.s9)
        OR (CHAR_LENGTH(a.s9) > 3 AND ABS(CHAR_LENGTH(a.s9) - CHAR_LENGTH(b.s9)) <= 3
            AND LEFT(a.s9, LEAST(CHAR_LENGTH(a.s9), CHAR_LENGTH(b.s9)) - 1)
              = LEFT(b.s9, LEAST(CHAR_LENGTH(a.s9), CHAR_LENGTH(b.s9)) - 1))
        OR (CHAR_LENGTH(a.s9) >= 3 AND CHAR_LENGTH(b.s9) >= 3
            AND (a.s9 LIKE CONCAT(b.s9, '%') OR b.s9 LIKE CONCAT(a.s9, '%')))
        OR ((a.s9 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s9 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s9 LIKE CONCAT(b.s9, '%') OR b.s9 LIKE CONCAT(a.s9, '%')))
    ))
    AND ((a.wc < 10) OR (
        (CHAR_LENGTH(a.s10) <= 3 AND a.s10 = b.s10)
        OR (CHAR_LENGTH(a.s10) > 3 AND ABS(CHAR_LENGTH(a.s10) - CHAR_LENGTH(b.s10)) <= 3
            AND LEFT(a.s10, LEAST(CHAR_LENGTH(a.s10), CHAR_LENGTH(b.s10)) - 1)
              = LEFT(b.s10, LEAST(CHAR_LENGTH(a.s10), CHAR_LENGTH(b.s10)) - 1))
        OR (CHAR_LENGTH(a.s10) >= 3 AND CHAR_LENGTH(b.s10) >= 3
            AND (a.s10 LIKE CONCAT(b.s10, '%') OR b.s10 LIKE CONCAT(a.s10, '%')))
        OR ((a.s10 IN ('sk', 'įd', 'ob', 'dž', 'įv') OR b.s10 IN ('sk', 'įd', 'ob', 'dž', 'įv'))
            AND (a.s10 LIKE CONCAT(b.s10, '%') OR b.s10 LIKE CONCAT(a.s10, '%')))
    ))
),
duplicate_exact_names AS (
    SELECT name
    FROM p
    GROUP BY name
    HAVING COUNT(*) > 1
),
exact_name_pairs AS (
    SELECT
        a.id AS id1,
        b.id AS id2,
        a.name AS name1,
        b.name AS name2,
        a.category_id,
        TRIM(SUBSTRING_INDEX(a.name, ',', -1)) AS suffix1,
        TRIM(SUBSTRING_INDEX(b.name, ',', -1)) AS suffix2,
        NULL AS suffix_norm,
        NULL AS word_count,
        TRIM(SUBSTRING_INDEX(a.name, ',', CHAR_LENGTH(a.name) - CHAR_LENGTH(REPLACE(a.name, ',', '')))) AS base1,
        TRIM(SUBSTRING_INDEX(b.name, ',', CHAR_LENGTH(b.name) - CHAR_LENGTH(REPLACE(b.name, ',', '')))) AS base2
    FROM p a
    JOIN duplicate_exact_names den ON den.name = a.name
    JOIN p b ON a.id < b.id AND a.name = b.name
),
duplicate_normalized_names AS (
    SELECT normalized_name
    FROM p
    GROUP BY normalized_name
    HAVING COUNT(*) > 1
        AND SUM(CASE WHEN name LIKE '% ,%' THEN 1 ELSE 0 END) > 0
),
normalized_name_pairs AS (
    SELECT
        a.id AS id1,
        b.id AS id2,
        a.name AS name1,
        b.name AS name2,
        a.category_id,
        TRIM(SUBSTRING_INDEX(a.name, ',', -1)) AS suffix1,
        TRIM(SUBSTRING_INDEX(b.name, ',', -1)) AS suffix2,
        NULL AS suffix_norm,
        NULL AS word_count,
        TRIM(SUBSTRING_INDEX(a.name, ',', CHAR_LENGTH(a.name) - CHAR_LENGTH(REPLACE(a.name, ',', '')))) AS base1,
        TRIM(SUBSTRING_INDEX(b.name, ',', CHAR_LENGTH(b.name) - CHAR_LENGTH(REPLACE(b.name, ',', '')))) AS base2
    FROM p a
    JOIN duplicate_normalized_names dnn ON dnn.normalized_name = a.normalized_name
    JOIN p b ON a.id < b.id
    WHERE a.normalized_name = b.normalized_name
    AND a.name <> b.name
    AND (
        a.name LIKE '% ,%'
        OR b.name LIKE '% ,%'
    )
)
SELECT * FROM fuzzy_pairs
UNION ALL
SELECT * FROM exact_name_pairs
UNION ALL
SELECT * FROM normalized_name_pairs
ORDER BY category_id, suffix_norm, word_count, base1
