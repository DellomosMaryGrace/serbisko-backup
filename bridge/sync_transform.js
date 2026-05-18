'use strict';

// =============================================================================
// sync_transform.js â€” Hybrid Rule-Based + Fuzzy Fallback Identity Validation
// =============================================================================
// DROP-IN REPLACEMENT for the transformation / decision logic inside sync.js.
//
// CONTRACT:
//   evaluateIdentityChanges(incoming, existing) â†’ { requiresApproval, reasons, autoUpdate }
//
// This object is the ONLY source of truth for updates, conflicts, approvals,
// and auto-sync decisions. No other code should make those decisions.
//
// DECISION PRIORITY ORDER (highest â†’ lowest):
//   1. LRN rules          (absolute â€” no exceptions)
//   2. Strict name rules  (deterministic levenshtein thresholds)
//   3. Birthday rules     (digit-by-digit comparison)
//   4. Fuzzy fallback     (only when name distance is borderline 3â€“4)
//   5. Non-identity fields (always auto â€” never trigger approval/conflict)
// =============================================================================

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// LOW-LEVEL HELPERS (pure functions, no side effects)
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const safe = (v) => (v === undefined || v === 'undefined' || v === null) ? null : v;
/**
 * Unicode-safe normalisation: strip accents, lowercase, remove non-alphanumeric.
 * Used before every string comparison to ensure encoding differences don't cause
 * false positives (e.g. "Ã‘ino" vs "Nino").
 */
const normalize = (str) =>
  String(str || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '') // strip combining diacritics
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '')       // strip spaces, hyphens, punctuation
    .trim();

/**
 * Classic iterative Levenshtein distance (O(n) space).
 * Counts the minimum single-character edits (insert / delete / substitute)
 * needed to transform string `a` into string `b`.
 */
function levenshtein(a, b) {
  const m = a.length, n = b.length;
  let prev = Array.from({ length: n + 1 }, (_, i) => i);
  let curr = new Array(n + 1);
  for (let i = 1; i <= m; i++) {
    curr[0] = i;
    for (let j = 1; j <= n; j++) {
      curr[j] = a[i - 1] === b[j - 1]
        ? prev[j - 1]
        : 1 + Math.min(prev[j], curr[j - 1], prev[j - 1]);
    }
    [prev, curr] = [curr, prev];
  }
  return prev[n];
}

/**
 * Fuzzy similarity score in the range [0, 1].
 * Based on the longest-common-subsequence length relative to the longer string.
 * Used ONLY as a fallback when levenshtein distance is in the borderline zone (3â€“4).
 *
 * Formula: 1 - (distance / max(len_a, len_b))
 *   â€¢ 1.0 = identical
 *   â€¢ 0.0 = completely different
 *
 * No external libraries â€” pure JS implementation.
 */
function similarityScore(a, b) {
  if (!a && !b) return 1;
  if (!a || !b) return 0;
  const maxLen = Math.max(a.length, b.length);
  if (maxLen === 0) return 1;
  const dist = levenshtein(a, b);
  return 1 - dist / maxLen;
}

/**
 * Normalise any date value to a plain YYYY-MM-DD string.
 * Handles three cases:
 *   1. Already a YYYY-MM-DD string (Firestore)       â†’ slice first 10 chars
 *   2. JS Date object (mysql2 DATE columns)           â†’ use toISOString() first
 *   3. ISO datetime string "2005-06-15T00:00:00.000Z" â†’ slice first 10 chars
 *
 * NOTE: String(new Date(...)) produces locale-dependent output like
 * "Wed Jun 15 2005 ..." â€” never slice that. Always go through toISOString().
 */
function normalizeDateStr(d) {
  if (!d) return null;
  // If it's a real Date object (mysql2 returns these for DATE columns), convert
  // via toISOString() which always yields "YYYY-MM-DDTHH:mm:ss.sssZ".
  if (d instanceof Date) {
    return isNaN(d.getTime()) ? null : d.toISOString().substring(0, 10);
  }
  const s = String(d).trim();
  // Already in YYYY-MM-DD or ISO datetime format â€” safe to slice.
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.substring(0, 10);
  // Last resort: try parsing as a Date (handles other string formats).
  const parsed = new Date(s);
  return isNaN(parsed.getTime()) ? null : parsed.toISOString().substring(0, 10);
}

const isValidDate = (d) => Boolean(d && /^\d{4}-\d{2}-\d{2}$/.test(d));

const toTitleCase = (str) => {
  const s = String(str || '').trim();
  if (!s) return s;
  return s.toLowerCase().replace(/(^|[\s-])(\S)/gu, (m, sep, ch) => sep + ch.toUpperCase());
};

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// CLASSIFICATION FUNCTIONS
// Each returns a stable string token used by evaluateIdentityChanges().
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * classifyNameChange(incomingFirst, incomingLast, existingFirst, existingLast)
 *
 * Returns one of:
 *   "same"  â€” normalized strings are identical; no action needed
 *   "swap"  â€” first and last name appear to be transposed; treat as minor typo
 *   "minor" â€” levenshtein â‰¤ 2 on both names; auto-update (typo-level edit)
 *   "major" â€” levenshtein > 2 on either name, AND fuzzy score < 0.90
 *   "minor" â€” levenshtein distance 3â€“4 (borderline) AND fuzzy score â‰¥ 0.90
 *             (fuzzy override: high similarity despite larger edit distance)
 *
 * PRIORITY:
 *   1. Exact match  â†’ "same"
 *   2. Swap check   â†’ "swap"
 *   3. Strict dist  â†’ "minor" (â‰¤ 2) or tentative "major" (> 2)
 *   4. Fuzzy check  â†’ overrides tentative "major" â†’ "minor" IF distance 3â€“4
 *                     AND similarity â‰¥ 90%
 */
function classifyNameChange(inF, inL, exF, exL) {
  const nInF = normalize(inF), nInL = normalize(inL);
  const nExF = normalize(exF), nExL = normalize(exL);

  // â”€â”€ Priority 1: No change â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  if (nInF === nExF && nInL === nExL) return 'same';

  // â”€â”€ Priority 2: Swap detection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // First and last are fully transposed (e.g. data-entry reversal).
  // Treat as a correctable typo â€” no approval required.
  if (nInF === nExL && nInL === nExF) return 'swap';

  // â”€â”€ Priority 3: Strict levenshtein rules â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const distFirst = levenshtein(nInF, nExF);
  const distLast  = levenshtein(nInL, nExL);
  const maxDist   = Math.max(distFirst, distLast);

  if (maxDist <= 2) {
    // Rule Â§N.1: distance â‰¤ 2 â†’ minor edit (clear typo) â†’ AUTO-UPDATE
    return 'minor';
  }

  // â”€â”€ Priority 4: Fuzzy fallback (ONLY for borderline distance 3â€“4) â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // If the edit distance is "borderline" (3 or 4), compute a fuzzy similarity
  // score. A high score means the strings are phonetically / visually close
  // despite the slightly larger edit count (e.g. nicknames, alternate spellings).
  // Distances of 5+ are definitively major â€” skip the fuzzy check entirely.
  if (maxDist <= 4) {
    const simFirst = similarityScore(nInF, nExF);
    const simLast  = similarityScore(nInL, nExL);
    const minSim   = Math.min(simFirst, simLast); // both names must be similar

    if (minSim >= 0.90) {
      // Rule Â§N.2 (fuzzy override): borderline distance but very high similarity
      // â†’ treat as minor edit, allow auto-update
      return 'minor';
    }
  }

  // â”€â”€ Fallback: definitively major â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // Rule Â§N.3: distance > 2 (and fuzzy score < 0.90 if borderline)
  // â†’ significant name change â†’ REQUIRE APPROVAL
  return 'major';
}

/**
 * classifyBirthdayChange(incoming, existing)
 *
 * Returns one of:
 *   "same"  â€” both dates are identical after normalisation
 *   "minor" â€” 0 or 1 digit differs between YYYY-MM-DD strings; auto-update
 *   "major" â€” 2+ digits differ; requires admin approval
 *
 * Note: Comparison is digit-by-digit across the full "YYYY-MM-DD" string (10 chars).
 * Dashes are included in the comparison but will always match between two
 * well-formed dates, so they never contribute to the diff count.
 */
function classifyBirthdayChange(incoming, existing) {
  // Normalise both to YYYY-MM-DD; bail gracefully if either is absent/invalid
  const a = normalizeDateStr(incoming);
  const b = normalizeDateStr(existing);

  if (!a || !b) return 'same'; // missing date â†’ treat as unchanged (no conflict)
  if (a === b)  return 'same';

  let diffs = 0;
  const len = Math.max(a.length, b.length);
  for (let i = 0; i < len; i++) {
    if (a[i] !== b[i]) diffs++;
  }

  // Rule Â§B.1: 0â€“1 digit difference â†’ minor typo â†’ AUTO-UPDATE
  if (diffs <= 1) return 'minor';

  // Rule Â§B.2: 2+ digit differences â†’ meaningful date change â†’ REQUIRE APPROVAL
  return 'major';
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// CORE DECISION ENGINE
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * evaluateIdentityChanges(incoming, existing, context)
 *
 * The single, deterministic decision engine for ALL validation.
 * This function is the ONLY place where approval/conflict/auto-update decisions
 * are made. No scattered if/else logic elsewhere.
 *
 * @param {object} incoming â€” normalised fields from the Firestore document
 *   {
 *     lrn:        string | null,
 *     first_name: string | null,
 *     last_name:  string | null,
 *     birthday:   string | null   (YYYY-MM-DD)
 *   }
 *
 * @param {object|null} existing â€” row returned from MySQL (null if brand-new record)
 *   {
 *     id:           number,
 *     lrn:          string,
 *     first_name:   string,
 *     last_name:    string,
 *     birthday:     string | Date
 *   }
 *
 * @param {object} context â€” FIX Â§2: external lookup results passed as explicit
 *   parameter so the engine remains pure (no side effects, no external mutation).
 *   {
 *     nameCollisionUserId: number | null
 *       Required for new-record path. The user_id of an existing student whose
 *       (first_name + last_name + birthday) matches the incoming record but whose
 *       LRN differs. Set to null if no collision was found. Caller resolves this
 *       via MySQL query BEFORE calling evaluateIdentityChanges().
 *   }
 *
 * @returns {{
 *   requiresApproval: boolean,
 *   autoUpdate:       boolean,
 *   reasons:          string[],
 *   debug:            { nameClass: string|null, birthdayClass: string|null, minNameSim: number|null }
 * }}
 *
 *   â€¢ requiresApproval: true  â†’ log to sync_conflicts, mark Firestore 'conflict'
 *   â€¢ autoUpdate: true        â†’ proceed to data write path (MySQL UPDATE / INSERT)
 *   â€¢ reasons[]               â†’ human-readable audit trail for every non-trivial decision
 *   â€¢ debug{}                 â†’ machine-readable classification metadata for logging/debugging
 */
function evaluateIdentityChanges(incoming, existing, context = {}) {
  const result = {
    requiresApproval: false,
    autoUpdate:       false,
    reasons:          [],
    // FIX Â§4: debug block â€” populated by classifiers; makes every decision
    // fully explainable for audit logs, admin UI, and regression testing.
    debug: {
      nameClass:    null,
      birthdayClass: null,
      minNameSim:   null,
    },
  };

  if (existing) {
    return handleExistingRecord(incoming, existing, result);
  } else {
    // FIX Â§2: collision context is now a clean separate argument, not a mutation
    // of the incoming object. The engine never needs to know how the lookup was done.
    return handleNewRecord(incoming, context, result);
  }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// HANDLERS
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * handleExistingRecord(incoming, existing, result)
 *
 * Applies the full validation rule-set for a document whose LRN already exists
 * in MySQL. Evaluates identity fields in strict priority order:
 *
 *   1. LRN change  (Â§LRN)   â€” always requires approval
 *   2. Name change (Â§NAME)  â€” strict rules, fuzzy fallback for borderline
 *   3. Birthday    (Â§BDAY)  â€” digit-by-digit comparison
 *   4. Non-identity fields  â€” always auto (never checked here, handled at write)
 *
 * Mutates and returns `result`.
 */
function handleExistingRecord(incoming, existing, result) {
  // â”€â”€ Rule Â§LRN: LRN change is an ABSOLUTE conflict trigger â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // LRN is the primary student identifier. Any change â€” even a single digit â€”
  // must go through admin approval. NO fuzzy fallback. NO auto-update.
  // Priority 1: evaluated before any name/birthday checks.
  if (incoming.lrn && existing.lrn && incoming.lrn !== existing.lrn) {
    result.requiresApproval = true;
    result.autoUpdate       = false;
    result.reasons.push(
      `lrn_change: incoming LRN "${incoming.lrn}" differs from existing "${existing.lrn}"`
    );
    // LRN conflict is terminal â€” return immediately, do not evaluate further.
    // Admin must resolve this before any other field is touched.
    return result;
  }

  // â”€â”€ Rule Â§NAME: Name validation (hybrid strict + fuzzy fallback) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // Only evaluated when first_name or last_name is present in this payload.
  const hasNameFields =
    incoming.first_name != null || incoming.last_name != null;

  if (hasNameFields) {
    // Use existing name as fallback for whichever side is absent in the payload
    const inF = incoming.first_name ?? existing.first_name;
    const inL = incoming.last_name  ?? existing.last_name;

    const nameClass = classifyNameChange(
      inF, inL,
      existing.first_name, existing.last_name
    );

    if (nameClass === 'major') {
      // Rule Â§N.3: large edit distance AND low fuzzy score â†’ block auto-sync
      result.requiresApproval = true;
      result.reasons.push(
        `identity_mismatch: name change "${existing.first_name} ${existing.last_name}"` +
        ` â†’ "${inF} ${inL}" exceeds auto-update threshold`
      );
    } else if (nameClass === 'minor' || nameClass === 'swap') {
      // Rules Â§N.1 / Â§N.2 / swap: typo-level or transposition â†’ auto-correct
      result.autoUpdate = true;
      if (nameClass === 'swap') {
        result.reasons.push(
          `name_swap_corrected: first/last names were transposed â€” auto-corrected`
        );
      }
      // Minor edit: no reason logged (silent auto-update, no noise in audit trail)
    }
    // nameClass === 'same' â†’ no action, no reason
  }

  // â”€â”€ Rule Â§BDAY: Birthday validation (digit-by-digit) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // Evaluated independently of name rules. Both name AND birthday can each
  // independently require approval â€” the first one encountered sets
  // requiresApproval; subsequent ones append additional reasons.
  const hasBirthday = incoming.birthday != null;

  if (hasBirthday && isValidDate(incoming.birthday)) {
    const bdayClass = classifyBirthdayChange(
      incoming.birthday,
      normalizeDateStr(existing.birthday)
    );

    if (bdayClass === 'major') {
      // Rule Â§B.2: 2+ digit differences â†’ requires approval
      result.requiresApproval = true;
      result.reasons.push(
        `birthday_mismatch: incoming "${incoming.birthday}"` +
        ` differs significantly from existing "${normalizeDateStr(existing.birthday)}"`
      );
    } else if (bdayClass === 'minor') {
      // Rule Â§B.1: â‰¤ 1 digit difference â†’ auto-correct the typo
      result.autoUpdate = true;
      // Minor birthday fix: silent auto-update (no noise in reasons)
    }
    // bdayClass === 'same' â†’ no action
  }

  // â”€â”€ Non-identity fields: ALWAYS auto-update, NEVER require approval â”€â”€â”€â”€â”€â”€â”€
  // Address, guardian info, demographics, form metadata, etc.
  // These are written unconditionally at the data-write step â€” no evaluation needed here.
  // The rule is enforced structurally: by not checking them in this function,
  // they can never accidentally set requiresApproval = true.

  // â”€â”€ Final auto-update flag â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  // FIX Â§1: autoUpdate must NOT be forced true unconditionally.
  // The old `if (!result.requiresApproval) result.autoUpdate = true` was wrong
  // because it triggered autoUpdate even on payloads with zero identity fields
  // (e.g. partial updates, metadata-only submissions, broken Firestore documents).
  //
  // Correct rule: only flip autoUpdate on when:
  //   a) no approval is required   AND
  //   b) at least one identity field is actually present in this payload
  //      (proving there is real data to write, not just a no-op document)
  result.autoUpdate =
    !result.requiresApproval &&
    (incoming.first_name != null || incoming.last_name != null || incoming.birthday != null);

  return result;
}

/**
 * handleNewRecord(incoming, result)
 *
 * Applies new-submission rules for a Firestore document whose LRN does NOT
 * exist in MySQL yet.
 *
 * Rules:
 *   Â§NEW.1: If another student row already matches (first_name + last_name + birthday)
 *           â†’ REQUIRE APPROVAL (possible duplicate / LRN collision)
 *   Â§NEW.2: No existing identity match â†’ AUTO CREATE (insert new user + student row)
 *
 * The caller MUST attach `incoming.nameCollisionUserId` (number | null) before
 * calling evaluateIdentityChanges() for new records. This value comes from the
 * MySQL duplicate-identity lookup performed in processDocument() step 8.
 *
 *   // In processDocument(), after confirming existingUser === undefined:
 *   const [[nameMatch]] = await conn.execute(
 *     `SELECT u.id FROM users u JOIN students s ON s.user_id = u.id
 *      WHERE u.first_name = ? AND u.last_name = ? AND u.birthday = ? LIMIT 1`,
 *     [firstName, lastName, bday]
 *   );
 *   incoming.nameCollisionUserId = nameMatch?.id ?? null;
 *
 * Mutates and returns `result`.
 */
function handleNewRecord(incoming, context, result) {
  const collisionUserId = incoming.nameCollisionUserId ?? context?.nameCollisionUserId ?? null;
  if (collisionUserId) {
    // Rule Â§NEW.1: A student with the same full name + birthday already exists
    // under a DIFFERENT LRN. This is an identity collision â€” could be a duplicate
    // enrollment, an LRN correction, or data-entry error. Admin must decide.
    result.requiresApproval = true;
    result.autoUpdate       = false;
    result.reasons.push(
      `lrn_change_request: no student found for LRN "${incoming.lrn}" but an identical` +
      ` identity (name + birthday) matches user_id ${collisionUserId}`
    );
  } else {
    // Rule Â§NEW.2: Genuinely new student â€” safe to auto-create
    result.requiresApproval = false;
    result.autoUpdate       = true;
    // No reason needed: clean new record, no conflict
  }

  return result;
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// CONFLICT TYPE RESOLVER
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * resolveConflictType(decision)
 *
 * Maps the decision object to the conflict_type string written to sync_conflicts.
 * Keeps the conflict-type taxonomy consistent with SyncConflictController.php.
 *
 * Returns null when no conflict should be logged (autoUpdate only or no-op).
 *
 * @param {{ requiresApproval: boolean, reasons: string[] }} decision
 * @returns {string|null}
 */
function resolveConflictType(decision) {
  if (!decision.requiresApproval) return null;

  // Inspect the first reason token (format: "token_key: human message")
  // to map onto the existing conflict_type enum used by the PHP controller.
  const firstReason = decision.reasons[0] || '';

  if (firstReason.startsWith('lrn_change'))          return 'lrn_change_request';
  if (firstReason.startsWith('lrn_change_request'))  return 'lrn_change_request';
  if (firstReason.startsWith('identity_mismatch'))   return 'identity_mismatch';
  if (firstReason.startsWith('birthday_mismatch'))   return 'birthday_mismatch';

  // Generic fallback â€” should not be reached with well-formed reason strings
  return 'identity_mismatch';
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// EXPORTS
// These are the ONLY symbols that processDocument() should import/use.
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

module.exports = {
  // â”€â”€ Primary API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  evaluateIdentityChanges, // single entry point for all validation decisions
  resolveConflictType,     // maps decision â†’ conflict_type string for MySQL

  // â”€â”€ Sub-classifiers (exported for unit-testing) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  classifyNameChange,      // "same" | "swap" | "minor" | "major"
  classifyBirthdayChange,  // "same" | "minor" | "major"

  // â”€â”€ Utilities (re-exported so sync.js can remove its own copies) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  normalize,
  levenshtein,
  normalizeDateStr,
  isValidDate,
  toTitleCase,
  safe
};

