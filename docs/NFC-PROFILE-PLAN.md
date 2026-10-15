# NFC Smart Card, QR Profile & Participant Management — plan (inside BICS)

Decisions (2026-10-13): built **inside BICS**, **Laravel + Blade** (same admin panel, roles and permissions),
delivered in phases. Each phase is usable on its own.

## 1. How it fits into BICS

| Prompt concept | In BICS |
|---|---|
| Event | existing `events` table (Admin › Events) |
| Event registration | existing `registrations` (one per person per event; check-in, raffle, certificates) |
| **Participant (permanent profile)** | **new `participants` table** — one per person, across all events, permanent ID `MBR-000001` and URL `/p/MBR-000001` |
| Admin users / roles / permissions | existing `users`, `roles`, `role_permissions` (+ new permissions) |
| Registration QR (check-in) | unchanged — the existing 6-digit number / `/verify/{code}` |
| **Profile QR / NFC URL** | **new** — points to `/p/{code}`; never changes when the profile changes |

A registration is linked to its participant (`registrations.participant_id`), matched by email. One person registering
for 2026 and 2027 keeps the same participant ID, profile URL, QR code and NFC card.

## 2. Database (all phases)

**Phase 1**
- `participants` — id, code (unique, e.g. MBR-000001), sequence, status (active / suspended), title, first_name, last_name,
  designation, organization, department, category, country, photo, logo, email, mobile, phone, whatsapp, address, map_url,
  website, socials (JSON: facebook, linkedin, instagram, youtube, x, other), bio, expertise, custom_fields (JSON: label,
  value, public), visibility (JSON: field → public / private), editing_enabled, locked_fields (JSON), created_by,
  timestamps, deleted_at (soft delete).
- `registrations.participant_id` → participants.
- `profile_stats` — participant_id, day, metric (view_link, view_qr, view_nfc, vcard, qr_download, share, …), count.
  Daily counters only: no IP addresses, no visitor tracking (privacy-friendly).

**Phase 2 — NFC cards** (built 2026-10-13)
- `nfc_cards` — card_no (NFC-000001), token (the chip stores `/n/{token}`), uid (optional), participant_id,
  status (available / assigned / active / lost / damaged / deactivated), encode_status, verify_status, print_status,
  url_written, locked, encoded_at, verified_at, activated_at, printed_at, replaces_card_id, notes.
- `nfc_card_logs` — card history (who, when, what).
- Why `/n/{token}` and not the profile URL on the chip: a lost card can really be switched off (it then reveals nothing),
  and a card can be reassigned without rewriting it. The QR printed on the card stays the permanent profile URL.

**Phase 3 — Card Design Studio**
- `card_templates` — name, width_mm, height_mm, bleed_mm, safe_mm, front (JSON layers), back (JSON layers), locked
  layers, is_default, event_id / category, version, timestamps. `card_template_versions` for history.

**Phase 4 — Bulk printing**
- `print_jobs` — batch id, template (+ version), participants, settings (paper, cards per sheet, crop marks, bleed,
  sides, format), status, file paths, created_by, created_at. `print_job_items` — participant, card, status.

Built (phase 4): `card_print_jobs` + `card_print_job_items` (participant, template + version, NFC card, printed_at).
Print files are made on the server with TCPDF (App\Support\CardPdf mirrors card-renderer.js): vector PDF, one card per
page or N-up sheets (A4 / A3 / Letter / SRA3 / custom) with crop marks, bleed and duplex backs (long / short edge), a CSV
list (page, participant, NFC link) and an optional ZIP with one PDF per participant. Fonts: `php artisan cards:fonts`
(stored in storage/app/card-fonts). Raster PNG/JPG, EPS and CMYK output still need Imagick / Ghostscript (not installed).

Built (phase 5): `/my` — sign-in with an emailed one-time link (opens a confirm page, so mail scanners cannot use it up)
or 6-digit code (5 tries), 20-minute expiry, max 3 emails / 15 min, same answer for unknown emails; `participant` auth
guard. Overview (profile, QR / vCard downloads, NFC card status, card preview, 30-day visits, events & certificates) and
edit page (public/private per field, photo/logo upload). Admin: Participants › Dashboard settings (sign-in on/off,
editing on/off, fields locked for everyone — name & category by default), per-participant lock + "Email a sign-in link",
change history (`participant_changes`, participant and admin edits). The participant's own visits are not counted.
Dashboard v2: app shell (Overview · My events · Card & QR · Profile · Security). Optional password (sign in with
password or emailed code), password change signs out other devices (`participants.session_version`), sign-in history
(`participant_logins`), date of birth (private, from registrations) for age; My events shows per registration the
approval, check-in, ID card PDF (approved only, own registrations only), certificate, raffle prizes and gift.

Built (phase 6): `audit_logs` — every admin write request (AuditTrail middleware, secrets masked) plus admin sign-in /
sign-out / failed sign-in; System › Audit log (filters, details, CSV). Reports › Participant analytics (views by
source over time, contacts saved, clicks, top profiles, organizations / countries / categories, events, dashboard use,
NFC & printing; per event; CSV). Participants › Organizations & categories (spelling variants grouped, rename / merge).
System › Platform settings (ID prefix, retention of audit log / visit counts / sign-ins; `php artisan bics:prune`,
scheduled daily). New permissions: reports.analytics, audit.view, system.settings.

**Phase 5–6**
- participant login (password / magic link), `audit_logs` (admin actions), organisations & categories, settings.

## 3. Phases

| Phase | Delivers | Needs |
|---|---|---|
| **1. Profiles & QR** ✅ | participant database (auto-created from registrations), permanent ID + URL, public mobile profile with vCard / share / call / WhatsApp / maps / socials, per-field public/private, QR (SVG, PNG, EPS vector), admin participant management (search, filters, create, edit, suspend, soft delete + restore), privacy-friendly visit counters | nothing extra |
| **2. NFC cards** ✅ | card inventory, assign / encode / verify / activate / deactivate / replace / reassign, history, duplicate-assignment protection, **encode from the browser with Web NFC** | Web NFC works only in **Chrome on Android** with NFC tags (NTAG213/215/216). USB desk encoders (e.g. ACR122U) need their own software — BICS gives them the URL to write. Chip UID only when the device exposes it. |
| **3. Card Design Studio** ✅ | front/back drag-and-drop editor (text, placeholders, photo, logo, QR, shapes, icons), CR80 85.6×54 mm and custom sizes, bleed / safe area / crop marks, templates per event / category, lock layers, duplicate, versions, preview with real participants | nothing extra (JS editor in the browser) |
| **4. Bulk printing & export** ✅ | multi-participant preview + validation (missing photo / data, duplicates), **vector PDF** (exact mm, sheets of N cards on A4/custom, crop marks, bleed, front/back), **SVG**, ZIP of individual files + CSV mapping, print-job history, "only not yet printed", reprints | **300-DPI PNG/JPG** of full card artwork needs Imagick or headless Chrome on the server. **EPS** of card artwork and **CMYK** need Ghostscript (+ ICC profile). Not installed here — the plan will use them when available and say so clearly otherwise. |
| **5. Participant dashboard** ✅ | secure login, edit permitted fields, photo/logo, preview profile & card, download QR / vCard, card status, own stats; admin can lock fields / disable editing | nothing extra |
| **6. Reports & admin** ✅ | dashboard statistics, analytics (views by source, vCard downloads, top profiles), counts by event / organisation / category, audit logs, organisations & categories, settings (ID prefix, domain, retention) | nothing extra |

## 4. Security
- New permissions: `participants.manage`, `participants.delete`, `nfc.manage`, `cards.design`, `cards.print`.
- Public profile shows only fields marked public; never internal IDs, NFC UIDs, card records or stats.
- Suspended participants: profile URL shows "not available" (QR and NFC keep pointing to the same URL, so re-activating
  restores everything).
- Uploads: images only, random file names, script execution blocked in upload folders.
- Rate limits on public endpoints; server-side validation everywhere.

## 5. Acceptance (prompt §15) — where each is met
1 ID/URL/QR → Phase 1 · 2 NFC opens profile → Phase 2 (+ hardware) · 3 manage records/cards → 1–2 · 4 public profile →
1 · 5 design/preview → 3 · 6 import/validate → 1 (registrations) + 4 (Excel import) · 7–8 print files → 4 · 9 permissions
→ 1–6 · 10 stable URLs → 1 · 11 print/replacement history → 2, 4 · 12 responsive/secure → all.
