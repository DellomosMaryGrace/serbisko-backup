'use strict';
require('dotenv').config();

const admin          = require('firebase-admin');
const mysql          = require('mysql2/promise');
const bcrypt         = require('bcryptjs');
const serviceAccount = require('./serviceAccountKey.json');

// Import the new Decision Engine and shared helpers
const { 
  evaluateIdentityChanges, 
  resolveConflictType,
  safe,
  toTitleCase,
  isValidDate
} = require('./sync_transform');

// Initialize Firestore
admin.initializeApp({ credential: admin.credential.cert(serviceAccount) });
const db = admin.firestore();
console.log('ðŸ”¥ [FIRESTORE] Connected and listening for changes...');

// Initialize MySQL Pool
const pool = mysql.createPool({
  host:             process.env.DB_HOST     || '127.0.0.1',
  port:    parseInt(process.env.DB_PORT     || '3306'),
  user:             process.env.DB_USERNAME || 'root',
  password:         process.env.DB_PASSWORD || '',
  database:         process.env.DB_DATABASE || 'serbisko_clean',
  waitForConnections: true,
  connectionLimit:    10,
  queueLimit:          0,
  timezone:          'Z',
});

// Explicitly check MySQL Connection at startup
(async () => {
  try {
    const conn = await pool.getConnection();
    console.log('ðŸ¬ [MYSQL] Connection pool established successfully.');
    conn.release();
  } catch (err) {
    console.error('âŒ [MYSQL] Failed to connect to the database:', err.message);
    process.exit(1);
  }
})();

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// SANITIZATION & HELPERS
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

/**
 * Converts all undefined values in a flat object to null.
 */
function sanitizePayload(obj) {
  const out = {};
  for (const key of Object.keys(obj)) {
    const val = obj[key];
    out[key] = (val === undefined || val === 'undefined') ? null : val;
  }
  return out;
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// KNOWN STUDENT-TABLE COLUMNS
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const STUDENT_COLUMNS = new Set([
  'sex', 'age', 'place_of_birth', 'mother_tongue',
  'curr_house_number', 'curr_street', 'curr_barangay', 'curr_city',
  'curr_province', 'curr_zip_code', 'curr_country',
  'is_perm_same_as_curr',
  'perm_house_number', 'perm_street', 'perm_barangay', 'perm_city',
  'perm_province', 'perm_zip_code', 'perm_country',
  'mother_last_name', 'mother_first_name', 'mother_middle_name', 'mother_contact_number',
  'father_last_name', 'father_first_name', 'father_middle_name', 'father_contact_number',
  'guardian_last_name', 'guardian_first_name', 'guardian_middle_name', 'guardian_contact_number',
]);

const EXCLUDED_FROM_EXTRA = new Set([
  'first_name', 'last_name', 'middle_name', 'extension_name',
  'birthday', 'lrn', 'password', 'role', 'updated_at', 'created_at',
  ...STUDENT_COLUMNS,
  'isSynced', 'extra_fields', 'form_id', 'submitted_at',
]);

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// CORE PROCESSOR
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

async function processDocument(docId, rawInput) {
  const terminalStates = [true, 'rejected', 'limit_reached'];
  if (terminalStates.includes(rawInput.isSynced)) return 'skipped';

  const raw = sanitizePayload(rawInput);
  const lrn = String(raw.lrn || '').trim();

  if (!lrn) {
    console.warn(`âš ï¸  [SKIP] Document ${docId} has no LRN â€” skipping.`);
    return 'skipped';
  }

  const conn = await pool.getConnection();

  try {
    await conn.beginTransaction();

    // â”€â”€ SCHOOL YEAR RESOLUTION â”€â”€
    // Fetch the school_year from the form definition itself.
    // This ensures submissions are tagged correctly even if multiple forms exist.
    let schoolYear = raw.school_year;
    if (raw.form_id) {
      const [[formDef]] = await conn.execute(
        `SELECT school_year FROM custom_forms WHERE id = ? LIMIT 1`,
        [raw.form_id]
      );
      if (formDef) {
        schoolYear = formDef.school_year;
      }
    }

    // Ultimate fallback if both form lookup and raw payload lack a year
    if (!schoolYear) {
      schoolYear = '2026-2027'; 
    }

    const bday       = isValidDate(raw.birthday) ? raw.birthday : null;
    const firstName  = toTitleCase(raw.first_name);
    const lastName   = toTitleCase(raw.last_name);
    const middleName = toTitleCase(raw.middle_name);

    const hasIdentityFields = 
      rawInput.hasOwnProperty('first_name') || 
      rawInput.hasOwnProperty('last_name')  || 
      rawInput.hasOwnProperty('middle_name') ||
      rawInput.hasOwnProperty('birthday');

    const flatRaw = { ...raw };
    if (flatRaw.extra_fields && typeof flatRaw.extra_fields === 'object') {
      Object.assign(flatRaw, flatRaw.extra_fields);
      delete flatRaw.extra_fields;
    }
    const extraFieldsOnly = {};
    for (const [key, val] of Object.entries(flatRaw)) {
      if (!EXCLUDED_FROM_EXTRA.has(key)) {
        extraFieldsOnly[key] = val;
      }
    }

    console.log(`[DEBUG] Processing LRN: ${lrn}, Target SY: ${schoolYear}`);
    const [rows] = await conn.execute(
      `SELECT u.id, u.first_name, u.last_name, u.birthday,
              s.id as student_id, s.lrn, s.is_manually_edited, s.school_year
       FROM users u
       JOIN students s ON s.user_id = u.id
       WHERE s.lrn = ?
       ORDER BY (s.school_year = ?) DESC, s.created_at DESC
       LIMIT 1`,
      [lrn, schoolYear]
    );
    const existingUser = rows[0];
    console.log(`[DEBUG] Existing User found: ${existingUser ? 'YES (ID: ' + existingUser.id + ')' : 'NO'}`);

    if (existingUser && existingUser.school_year === schoolYear && existingUser.is_manually_edited) {
      await conn.rollback();
      console.log(`ðŸ”’ [LOCKED] LRN ${lrn} is manually edited â€” skipping auto-sync.`);
      return 'skipped';
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // SUBMISSION LIMIT CHECK
    // Must run AFTER the lock check but BEFORE any identity validation or writes.
    // Counts all pre_enrollment rows ever recorded for this LRN (across versions),
    // which is the durable source of truth now that Firestore docs are deleted
    // after a successful sync.
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const SUBMISSION_LIMIT = 3;

    const [[{ submissionCount }]] = await conn.execute(
      `SELECT COUNT(*) AS submissionCount
       FROM pre_enrollments pe
       JOIN students s ON s.id = pe.student_id
       WHERE s.lrn = ? AND s.school_year = ?`,
      [lrn, schoolYear]
    );

    if (submissionCount >= SUBMISSION_LIMIT) {
      await conn.rollback();
      await db.collection('responses').doc(docId).update({
        isSynced:    'limit_reached',
        syncMessage: 'Submission limit reached. Please contact the admin if you need to make changes.',
      });
      console.log(`â›” [LIMIT] Submission limit reached for LRN: ${lrn} (${submissionCount}/${SUBMISSION_LIMIT})`);
      return 'limit_reached';
    }
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    let userId = existingUser?.id || null;

    // --- Step 7 & 8: The Refactored Decision Engine ---
    let incomingIdentity = {
      lrn: lrn,
      first_name: firstName,
      last_name: lastName,
      birthday: bday,
      nameCollisionUserId: null, 
    };

    if (!existingUser) {
      const [[nameMatch]] = await conn.execute(
        `SELECT u.id FROM users u 
         JOIN students s ON s.user_id = u.id
         WHERE u.first_name = ? AND u.last_name = ? AND u.birthday = ? 
         LIMIT 1`,
        [safe(firstName), safe(lastName), safe(bday)]
      );
      incomingIdentity.nameCollisionUserId = nameMatch?.id ?? null;
      if (nameMatch) userId = nameMatch.id;
    }

    const decision = evaluateIdentityChanges(incomingIdentity, existingUser ?? null);
    const conflictType = resolveConflictType(decision);

    if (conflictType) {
      await conn.execute(
        `INSERT INTO sync_conflicts 
          (lrn, school_year, existing_user_id, incoming_data_json, conflict_type, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ON DUPLICATE KEY UPDATE 
          conflict_type = VALUES(conflict_type), 
          incoming_data_json = VALUES(incoming_data_json), 
          status = 'pending'`,
        [lrn, schoolYear, safe(userId), JSON.stringify(raw), conflictType]
      );
      await conn.commit();
      await db.collection('responses').doc(docId).update({ isSynced: 'conflict' });
      console.log(`ðŸš¨ [CONFLICT] ${conflictType} for LRN: ${lrn} - ${decision.reasons.join('; ')}`);
      return 'conflict';
    }

    // --- Step 10: DATA WRITE PATH ---
    if (!userId) {
      const rawHash   = await bcrypt.hash(lrn, 10);
      const hashedLrn = rawHash.replace(/^\$2[ab]\$/, '$2y$');
      const [newUser] = await conn.execute(
        `INSERT INTO users (first_name, last_name, middle_name, birthday, password, role, created_at)
         VALUES (?, ?, ?, ?, ?, 'student', NOW())`,
        [safe(firstName), safe(lastName), safe(middleName), safe(bday), hashedLrn]
      );
      userId = newUser.insertId;
    } else if (hasIdentityFields) {
      await conn.execute(
        `UPDATE users SET
           first_name  = COALESCE(NULLIF(?, ''), first_name),
           last_name   = COALESCE(NULLIF(?, ''), last_name),
           middle_name = COALESCE(NULLIF(?, ''), middle_name),
           birthday    = COALESCE(?, birthday),
           updated_at  = NOW()
         WHERE id = ?`,
        [safe(firstName), safe(lastName), safe(middleName), safe(bday), userId]
      );
    }

    const studentUpdateFields = {};
    for (const col of STUDENT_COLUMNS) {
      if (rawInput.hasOwnProperty(col)) {
        studentUpdateFields[col] = safe(raw[col]);
      }
    }
    if (rawInput.hasOwnProperty('sex')) studentUpdateFields['sex'] = safe(raw.sex);
    if (rawInput.hasOwnProperty('age')) studentUpdateFields['age'] = safe(raw.age);

    if ('is_perm_same_as_curr' in studentUpdateFields) {
      const v = String(studentUpdateFields['is_perm_same_as_curr'] || '').toLowerCase();
      studentUpdateFields['is_perm_same_as_curr'] = (v === 'yes' || v === '1' || v === 'true') ? 1 : 0;
    }

    const baseInsertCols  = ['user_id', 'lrn', 'school_year', 'updated_at'];
    const baseInsertVals  = [userId, lrn, schoolYear, new Date()];
    const extraCols       = Object.keys(studentUpdateFields);
    const extraVals       = Object.values(studentUpdateFields);

    const allCols = [...baseInsertCols, ...extraCols];
    const allVals = [...baseInsertVals, ...extraVals];

    const placeholders  = allVals.map(() => '?').join(', ');
    const colList       = allCols.join(', ');
    const updateClause  = [...extraCols, 'updated_at'].map(c => `${c} = VALUES(${c})`).join(', ');

    await conn.execute(
      `INSERT INTO students (${colList}) VALUES (${placeholders})
       ON DUPLICATE KEY UPDATE ${updateClause}`,
      allVals
    );

    let [[stu]] = await conn.execute(
      `SELECT id FROM students WHERE lrn = ? AND school_year = ?`,
      [lrn, schoolYear]
    );
    if (!stu) {
      const [[stuFallback]] = await conn.execute(
        `SELECT id FROM students WHERE user_id = ? ORDER BY id DESC LIMIT 1`,
        [userId]
      );
      stu = stuFallback;
    }

    const [[{ v }]] = await conn.execute(
      `SELECT COUNT(*) as v FROM pre_enrollments WHERE student_id = ?`,
      [stu.id]
    );

    await conn.execute(
      `INSERT INTO pre_enrollments (student_id, submission_version, responses, status, created_at)
       VALUES (?, ?, ?, 'Synced', NOW())`,
      [stu.id, v + 1, JSON.stringify(extraFieldsOnly)]
    );

    await conn.commit();
    await db.collection('responses').doc(docId).update({ isSynced: true });
    console.log(`âœ… [SYNCED] LRN ${lrn} â€” version ${v + 1} committed.`);
    return 'success';

  } catch (err) {
    if (conn) await conn.rollback();
    console.error(`âŒ [ERROR] DocID ${docId} | LRN ${lrn} | ${err.message}`);
    throw err;
  } finally {
    if (conn) conn.release();
  }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// CONFIG RELOAD LOGIC
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const fs = require('fs');
const path = require('path');

function reloadConfig() {
  const envPath = path.resolve(__dirname, '../.env');
  if (fs.existsSync(envPath)) {
    const envConfig = require('dotenv').parse(fs.readFileSync(envPath));
    for (const k in envConfig) {
      process.env[k] = envConfig[k];
    }
    console.log('â™»ï¸  [.ENV] Configuration reloaded from .env file.');
  }
}

// Watch .env for changes
const envPath = path.resolve(__dirname, '../.env');
if (fs.existsSync(envPath)) {
  fs.watchFile(envPath, (curr, prev) => {
    if (curr.mtime !== prev.mtime) {
      reloadConfig();
    }
  });
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// LISTENER & AUTO-RECONNECT
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

let unsubscribe = null;

/**
 * Performs a "sweep" of all pending documents.
 */
async function syncSweep() {
  console.log('ðŸ§¹ [SWEEP] Refreshing sync for pending and conflict documents...');
  try {
    const snap = await db.collection('responses')
      .where('isSynced', 'in', [false, 'conflict'])
      .get();
    
    if (snap.empty) {
      console.log('âœ¨ [SWEEP] No pending documents found.');
      return;
    }

    console.log(`ðŸ“¦ [SWEEP] Found ${snap.size} documents to process. Processing...`);
    for (const doc of snap.docs) {
      await processDocument(doc.id, doc.data()).catch(err => {
        console.error(`âŒ [SWEEP ERROR] Failed to process ${doc.id}:`, err.message);
      });
    }
    console.log('âœ… [SWEEP] Completed.');
  } catch (err) {
    console.error('âŒ [SWEEP ERROR] Sweep failed:', err.message);
  }
}

function startSync() {
  if (unsubscribe) {
    console.log('ðŸ”„ [SYNC] Restarting Firestore listener...');
    unsubscribe();
  }

  unsubscribe = db.collection('responses')
    .where('isSynced', '==', false)
    .onSnapshot(async (snap) => {
      // Logic for "refresh when new data added"
      const docChanges = snap.docChanges();
      if (docChanges.length > 0) {
        const addedCount = docChanges.filter(c => c.type === 'added').length;
        if (addedCount > 0) {
          console.log(`ðŸ†• [SYNC] Refreshing: ${addedCount} new submissions detected.`);
        }
      }

      for (const change of docChanges) {
        if (change.type === 'added' || change.type === 'modified') {
          try {
            await processDocument(change.doc.id, change.doc.data());
          } catch (err) {
            console.error(`âŒ [SYNC ERROR] Failed to process ${change.doc.id}:`, err.message);
          }
        }
      }
    }, (error) => {
      console.error('ðŸ”¥ [FIRESTORE ERROR] Snapshot listener failed:', error.message);
      console.log('â³ [SYNC] Attempting to reconnect in 5 seconds...');
      setTimeout(startSync, 5000);
    });
}

// Initial start
startSync();
syncSweep(); 

// Robust fallback: Run a sweep every 30 minutes to ensure total consistency
setInterval(syncSweep, 30 * 60 * 1000);

