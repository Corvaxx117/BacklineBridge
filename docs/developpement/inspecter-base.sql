-- Lecture seule : comptes et droits, sans afficher les hashes des mots de passe.
SELECT u.id, u.display_name, u.email, b.name AS groupe, m.role
FROM app_user u
JOIN band_membership m ON m.user_id = u.id
JOIN band b ON b.id = m.band_id
ORDER BY b.id, u.display_name;

-- Concerts et état de validation de la date.
SELECT c.id, c.date, c.venue, c.city, c.status, b.name AS groupe, c.patch_sheet_id
FROM concert c JOIN band b ON b.id = c.band_id
ORDER BY c.date;

-- Sources actives ; sheet_id distingue référence et copies de concerts.
SELECT id, sheet_id, channel_number, source_name, preferred_microphone, stand, notes
FROM patch_line WHERE active = 1
ORDER BY sheet_id, channel_number;

-- Sources retirées, conservées avec leur identifiant.
SELECT id, sheet_id, channel_number, source_name, deleted_at
FROM patch_line WHERE active IS NULL
ORDER BY deleted_at DESC;
