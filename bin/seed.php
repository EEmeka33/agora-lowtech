<?php
/*
 * Demo data: a low-tech workshop association, to try Agora or to use in a course.
 *
 *   php bin/seed.php           fills an EMPTY database (in English)
 *   php bin/seed.php --fr      same, in French
 *   php bin/seed.php --reset   deletes everything first (careful!)
 *
 * Every demo account uses the password: agora-demo-2026
 */
require dirname(__DIR__) . '/app/boot.php';

$fr = in_array('--fr', $argv, true);
$L = fn (string $en, string $frText) => $fr ? $frText : $en;
$locale = $fr ? 'fr' : 'en';
locale($locale);

if (in_array('--reset', $argv, true)) {
    db()->exec('PRAGMA foreign_keys = OFF');
    foreach (col("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $table) {
        db()->exec("DELETE FROM $table");
    }
    db()->exec('PRAGMA foreign_keys = ON');
} elseif ((int) val('SELECT COUNT(*) FROM users') > 0) {
    exit("The database already contains data. Run `php bin/seed.php --reset` to wipe it and load the demo.\n");
}

const PASSWORD = 'agora-demo-2026';
$now = now_utc();
$day = fn (int $offset, ?string $time = null) => date('Y-m-d', strtotime("$offset days")) . ($time ? " $time" : '');
$ago = fn (int $days, int $hours = 0) => gmdate('Y-m-d H:i:s', time() - $days * 86400 - $hours * 3600);
$hash = hash_password(PASSWORD);

transaction(function () use ($L, $locale, $now, $day, $ago, $hash, $fr) {
    // ─── People ────────────────────────────────────────────────────────────
    $people = [
        'camille' => ['Camille Martin', $L('public speaking, project management', 'prise de parole, gestion de projet')],
        'yanis' => ['Yanis Benali', $L('accounting, spreadsheets', 'comptabilité, tableurs')],
        'lea' => ['Léa Dubois', $L('writing, organisation', 'rédaction, organisation')],
        'thomas' => ['Thomas Nguyen', $L('woodwork, solar energy, electronics', 'menuiserie, énergie solaire, électronique')],
        'ines' => ['Inès Moreau', $L('event planning, logistics', "organisation d'événements, logistique")],
        'lucas' => ['Lucas Bernard', $L('drawing, video, bike mechanics', 'dessin, vidéo, mécanique vélo')],
        'sarah' => ['Sarah Cohen', $L('sewing, repairing small appliances', 'couture, réparation de petit électroménager')],
        'maxime' => ['Maxime Petit', $L('photography', 'photographie'), true],
        'chloe' => ['Chloé Laurent', null, true],
    ];
    $u = [];
    foreach ($people as $key => $p) {
        $u[$key] = insert('users', [
            'name' => $p[0],
            'email' => "$key@lowtech.example",
            'password_hash' => empty($p[2]) ? $hash : null,
            'password_changed_at' => empty($p[2]) ? $ago(100) : null,
            'skills' => $p[1],
            'locale' => $locale,
            'email_mode' => 'DAILY',
            'last_login_at' => empty($p[2]) ? $ago(random_int(0, 5)) : null,
            'created_at' => $ago(120),
            'updated_at' => $ago(120),
        ]);
    }

    // ─── The association ───────────────────────────────────────────────────
    $a = insert('associations', [
        'name' => $L('Low-tech Workshop Collective', 'Atelier Low-tech du Quartier'),
        'short_name' => $L('LTWC', 'ALQ'),
        'tagline' => $L('Useful, accessible and sustainable technology, made together', 'Des techniques utiles, accessibles et durables, fabriquées ensemble'),
        'purpose' => $L(
            'Promote low-tech solutions through workshops, a monthly repair café and open tutorials: solar cookers, rainwater collectors, bike-powered machines, repairs.',
            'Promouvoir les low-tech par des ateliers, un repair café mensuel et des tutoriels ouverts : cuiseurs solaires, récupérateurs d\'eau de pluie, machines à pédales, réparations.'
        ),
        'legal_form' => 'Loi 1901',
        'registration_number' => 'W691234567',
        'founded_at' => $day(-300),
        'email' => 'hello@lowtech.example',
        'website' => 'https://lowtech.example',
        'address' => $L('12 Workshop Street', '12 rue de l\'Atelier'),
        'postal_code' => '69007',
        'city' => 'Lyon',
        'country' => 'France',
        'locale' => $locale,
        'currency' => 'EUR',
        'membership_fee_cents' => 1000,
        'volunteer_hour_cents' => 1200,
        'fiscal_year_start_month' => 1,
        'created_at' => $ago(300),
        'updated_at' => $ago(10),
    ]);
    $roles = [
        'camille' => ['ADMIN', 'PRESIDENT', $L('President', 'Présidente')],
        'yanis' => ['MANAGER', 'TREASURER', $L('Treasurer', 'Trésorier')],
        'lea' => ['MANAGER', 'SECRETARY', $L('Secretary', 'Secrétaire')],
        'thomas' => ['MEMBER', 'VICE_PRESIDENT', $L('Workshops lead', 'Responsable des ateliers')],
        'ines' => ['MANAGER', null, $L('Events lead', 'Responsable des événements')],
        'lucas' => ['MEMBER', null, $L('Communication', 'Communication')],
        'sarah' => ['MEMBER', null, $L('Repair café lead', 'Responsable du repair café')],
        'maxime' => ['MEMBER', null, null],
        'chloe' => ['MEMBER', null, null],
    ];
    foreach ($roles as $key => [$role, $board, $title]) {
        insert('members', ['association_id' => $a, 'user_id' => $u[$key], 'role' => $role, 'board_position' => $board, 'title' => $title,
            'joined_at' => $day(-random_int(30, 290)), 'created_at' => $ago(200)]);
    }

    // ─── Poles ─────────────────────────────────────────────────────────────
    $poleDefs = [
        'workshops' => [$L('Workshops', 'Ateliers'), $L('Build low-tech objects together: solar cookers, dryers, pedal machines.', 'Fabriquer ensemble des objets low-tech : cuiseurs solaires, séchoirs, machines à pédales.'), ['thomas' => 'LEAD', 'lucas' => 'MEMBER', 'maxime' => 'MEMBER', 'camille' => 'MEMBER']],
        'repair' => [$L('Repair café', 'Repair café'), $L('Once a month, repair rather than throw away.', 'Une fois par mois, réparer plutôt que jeter.'), ['sarah' => 'LEAD', 'thomas' => 'MEMBER', 'chloe' => 'MEMBER']],
        'docs' => [$L('Tutorials', 'Tutoriels'), $L('Write and share how-to guides under a free licence.', 'Écrire et partager des guides de fabrication sous licence libre.'), ['lea' => 'LEAD', 'lucas' => 'MEMBER']],
        'comm' => [$L('Communication', 'Communication'), $L('Posters, newsletter, social media, welcoming newcomers.', 'Affiches, lettre d\'information, réseaux, accueil des nouveaux.'), ['lucas' => 'LEAD', 'ines' => 'MEMBER']],
        'treasury' => [$L('Treasury', 'Trésorerie'), $L('Accounts, membership fees, grants.', 'Comptes, cotisations, subventions.'), ['yanis' => 'LEAD', 'camille' => 'MEMBER']],
    ];
    $p = [];
    $i = 0;
    foreach ($poleDefs as $key => [$name, $mission, $team]) {
        $p[$key] = insert('poles', ['association_id' => $a, 'name' => $name, 'mission' => $mission, 'position' => $i++, 'created_at' => $ago(250), 'updated_at' => $ago(250)]);
        foreach ($team as $who => $role) {
            insert('pole_members', ['pole_id' => $p[$key], 'user_id' => $u[$who], 'role' => $role, 'joined_at' => $ago(200)]);
        }
    }

    // ─── Founding checklist: mostly done ───────────────────────────────────
    require_once APP . '/pages/welcome.php';
    add_foundation_template($a, $locale);
    $steps = col('SELECT id FROM foundation_steps WHERE association_id = ? ORDER BY position', [$a]);
    foreach ($steps as $n => $id) {
        $status = $n < 11 ? 'DONE' : ($n === 11 ? 'IN_PROGRESS' : 'TODO');
        update('foundation_steps', ['status' => $status, 'completed_at' => $status === 'DONE' ? $ago(280 - $n * 10) : null,
            'assignee_id' => [$u['camille'], $u['lea'], $u['yanis']][$n % 3]], 'id = ?', [$id]);
    }

    // ─── Who does what ─────────────────────────────────────────────────────
    foreach ([
        [$L('Keep the accounts and file receipts', 'Tenir les comptes et classer les justificatifs'), 'MONTHLY', 'yanis', 'treasury'],
        [$L('Answer the association\'s emails', 'Répondre aux e-mails de l\'association'), 'WEEKLY', 'lea', null],
        [$L('Check and tidy the tool library', 'Vérifier et ranger l\'outilthèque'), 'MONTHLY', 'thomas', 'workshops'],
        [$L('Renew the insurance', 'Renouveler l\'assurance'), 'YEARLY', 'camille', null],
        [$L('Send the newsletter', 'Envoyer la lettre d\'information'), 'MONTHLY', 'lucas', 'comm'],
    ] as [$title, $freq, $who, $pole]) {
        insert('responsibilities', ['association_id' => $a, 'title' => $title, 'frequency' => $freq, 'owner_id' => $u[$who],
            'pole_id' => $pole ? $p[$pole] : null, 'created_at' => $ago(150), 'updated_at' => $ago(150)]);
    }

    // ─── Projects, milestones and tasks ────────────────────────────────────
    $projects = [
        'solar' => [$L('Solar cooker workshops', 'Ateliers cuiseurs solaires'), 'IN_PROGRESS', 'workshops', 'thomas', -40, 50, 30000,
            $L('Four Saturday workshops where each participant builds a box-type solar cooker from salvaged materials.', 'Quatre ateliers le samedi où chaque participant fabrique un cuiseur solaire de type boîte avec des matériaux de récupération.')],
        'blender' => [$L('Bike-powered blender for events', 'Blender à pédales pour les événements'), 'PLANNED', 'workshops', 'lucas', 10, 90, 8000,
            $L('A smoothie bike to show how much energy our devices use.', 'Un vélo à smoothie pour montrer combien d\'énergie consomment nos appareils.')],
        'repair' => [$L('Monthly repair café', 'Repair café mensuel'), 'IN_PROGRESS', 'repair', 'sarah', -120, 240, 15000,
            $L('Every first Saturday: volunteers help neighbours repair their objects.', 'Chaque premier samedi : des bénévoles aident les voisins à réparer leurs objets.')],
        'wiki' => [$L('Open tutorials', 'Tutoriels ouverts'), 'ON_HOLD', 'docs', 'lea', -60, 20, null,
            $L('Publish our build guides as printable pages under a free licence.', 'Publier nos guides de fabrication en pages imprimables sous licence libre.')],
        'rain' => [$L('Rainwater collector at the community garden', 'Récupérateur d\'eau de pluie au jardin partagé'), 'COMPLETED', 'workshops', 'thomas', -200, -120, 20000,
            $L('A 1000-litre collector made from a reused IBC tank.', 'Un récupérateur de 1000 litres fait d\'une cuve IBC réemployée.')],
    ];
    $pr = [];
    foreach ($projects as $key => [$title, $status, $pole, $lead, $start, $due, $budget, $desc]) {
        $pr[$key] = insert('projects', ['association_id' => $a, 'title' => $title, 'description' => $desc, 'status' => $status,
            'priority' => $key === 'solar' ? 'HIGH' : 'MEDIUM', 'start_date' => $day($start), 'due_date' => $day($due), 'budget_cents' => $budget,
            'pole_id' => $p[$pole], 'lead_id' => $u[$lead], 'created_at' => $ago(-$start + 5), 'updated_at' => $ago(3)]);
        insert('project_members', ['project_id' => $pr[$key], 'user_id' => $u[$lead], 'joined_at' => $ago(100)]);
    }
    foreach (['camille', 'maxime', 'lucas'] as $who) {
        q('INSERT OR IGNORE INTO project_members (project_id, user_id, role, joined_at) VALUES (?, ?, ?, ?)', [$pr['solar'], $u[$who], $who === 'maxime' ? $L('Photos', 'Photos') : null, $ago(30)]);
    }
    foreach ([
        ['solar', $L('Plans printed and tested', 'Plans imprimés et testés'), -20, 1],
        ['solar', $L('First workshop', 'Premier atelier'), -6, 1],
        ['solar', $L('Exhibition at the town fair', 'Exposition à la fête du quartier'), 45, 0],
        ['repair', $L('Insurance for the repair café', 'Assurance du repair café'), -90, 1],
        ['blender', $L('Find a second-hand bike', 'Trouver un vélo d\'occasion'), 25, 0],
    ] as $n => [$proj, $title, $due, $done]) {
        insert('milestones', ['project_id' => $pr[$proj], 'title' => $title, 'due_date' => $day($due), 'done' => $done, 'done_at' => $done ? $ago(abs($due)) : null, 'position' => $n, 'created_at' => $ago(60)]);
    }
    $tasks = [
        [$L('Collect cardboard boxes and aluminium foil', 'Récupérer des cartons et du papier aluminium'), 'DONE', 'solar', 'maxime', -10, 'MEDIUM'],
        [$L('Buy glass panes for the cookers', 'Acheter les vitres des cuiseurs'), 'IN_PROGRESS', 'solar', 'thomas', 3, 'HIGH'],
        [$L('Write the participant handout', 'Rédiger le livret participant'), 'REVIEW', 'solar', 'camille', 5, 'MEDIUM'],
        [$L('Book the courtyard for the second workshop', 'Réserver la cour pour le deuxième atelier'), 'TODO', 'solar', 'ines', 8, 'MEDIUM'],
        [$L('Measure the cooking temperature with a thermometer', 'Mesurer la température de cuisson au thermomètre'), 'TODO', 'solar', 'lucas', 14, 'LOW'],
        [$L('Sort the donated tools', 'Trier les outils donnés'), 'TODO', 'repair', 'sarah', -2, 'HIGH'],
        [$L('Print repair tracking sheets', 'Imprimer les fiches de suivi des réparations'), 'DONE', 'repair', 'chloe', -15, 'LOW'],
        [$L('Count repaired objects of last session', 'Compter les objets réparés de la dernière séance'), 'TODO', 'repair', 'sarah', 4, 'MEDIUM'],
        [$L('Draw the blender stand', 'Dessiner le support du blender'), 'TODO', 'blender', 'lucas', 30, 'MEDIUM'],
        [$L('Choose a free licence for the tutorials', 'Choisir une licence libre pour les tutoriels'), 'IN_PROGRESS', 'wiki', 'lea', 12, 'MEDIUM'],
        [$L('Prepare the general assembly report', 'Préparer le rapport moral de l\'assemblée générale'), 'TODO', null, 'camille', 18, 'URGENT'],
        [$L('Renew the bank card', 'Renouveler la carte bancaire'), 'TODO', null, null, 25, 'LOW'],
    ];
    $t = [];
    foreach ($tasks as $n => [$title, $status, $proj, $who, $due, $prio]) {
        $t[$n] = insert('tasks', ['association_id' => $a, 'title' => $title, 'status' => $status, 'priority' => $prio, 'due_date' => $day($due),
            'completed_at' => $status === 'DONE' ? $ago(5) : null, 'project_id' => $proj ? $pr[$proj] : null,
            'pole_id' => $proj ? (int) val('SELECT pole_id FROM projects WHERE id = ?', [$pr[$proj]]) : null,
            'assignee_id' => $who ? $u[$who] : null, 'creator_id' => $u['camille'], 'created_at' => $ago(20 - $n), 'updated_at' => $ago(2)]);
    }
    insert('task_comments', ['task_id' => $t[1], 'body' => $L('The glazier on Market Street gives us offcuts for free.', 'Le vitrier de la rue du Marché nous donne ses chutes gratuitement.'), 'author_id' => $u['thomas'], 'created_at' => $ago(2)]);
    insert('task_comments', ['task_id' => $t[1], 'body' => $L('Great! 3 mm glass is enough.', 'Super ! Du verre de 3 mm suffit.'), 'author_id' => $u['camille'], 'created_at' => $ago(1)]);
    insert('project_updates', ['project_id' => $pr['solar'], 'body' => $L("First workshop done: 9 participants, 7 cookers finished.\nNext: the glass panes.", "Premier atelier fait : 9 participants, 7 cuiseurs terminés.\nProchaine étape : les vitres."),
        'health' => 'ON_TRACK', 'progress' => 45, 'author_id' => $u['thomas'], 'created_at' => $ago(5)]);

    // ─── Requests between poles ────────────────────────────────────────────
    insert('requests', ['association_id' => $a, 'title' => $L('Design a poster for the next repair café', 'Créer une affiche pour le prochain repair café'),
        'description' => $L('A4, black and white so it can be photocopied cheaply.', 'A4, en noir et blanc pour la photocopier à bas coût.'), 'priority' => 'HIGH', 'due_date' => $day(6),
        'status' => 'OPEN', 'pole_id' => $p['comm'], 'from_pole_id' => $p['repair'], 'project_id' => $pr['repair'], 'requester_id' => $u['sarah'], 'created_at' => $ago(1), 'updated_at' => $ago(1)]);
    $taskId = insert('tasks', ['association_id' => $a, 'title' => $L('Lend two soldering irons to the repair café', 'Prêter deux fers à souder au repair café'), 'status' => 'IN_PROGRESS', 'priority' => 'MEDIUM',
        'due_date' => $day(4), 'pole_id' => $p['workshops'], 'assignee_id' => $u['thomas'], 'creator_id' => $u['sarah'], 'created_at' => $ago(3), 'updated_at' => $ago(2)]);
    insert('requests', ['association_id' => $a, 'title' => $L('Lend two soldering irons to the repair café', 'Prêter deux fers à souder au repair café'), 'priority' => 'MEDIUM', 'due_date' => $day(4),
        'status' => 'TAKEN', 'pole_id' => $p['workshops'], 'from_pole_id' => $p['repair'], 'requester_id' => $u['sarah'], 'taken_by_id' => $u['thomas'], 'task_id' => $taskId,
        'taken_at' => $ago(2), 'created_at' => $ago(3), 'updated_at' => $ago(2)]);
    insert('requests', ['association_id' => $a, 'title' => $L('Proofread the solar cooker tutorial', 'Relire le tutoriel du cuiseur solaire'), 'priority' => 'LOW', 'status' => 'OPEN',
        'pole_id' => $p['docs'], 'from_pole_id' => $p['workshops'], 'project_id' => $pr['solar'], 'requester_id' => $u['thomas'], 'created_at' => $ago(4), 'updated_at' => $ago(4)]);

    // ─── Events ────────────────────────────────────────────────────────────
    $events = [
        [$L('Annual general assembly', 'Assemblée générale annuelle'), 'GENERAL_ASSEMBLY', 21, '18:30', '21:00', $L('Community hall', 'Maison de quartier'),
            $L("1. Activity report\n2. Financial report\n3. Vote on the 2027 budget\n4. Board election", "1. Rapport moral\n2. Rapport financier\n3. Vote du budget 2027\n4. Élection du bureau"), null, null],
        [$L('Board meeting', 'Réunion de bureau'), 'BOARD_MEETING', -12, '19:00', '20:30', $L('At Camille\'s', 'Chez Camille'),
            $L("1. Repair café insurance\n2. Solar workshops budget", "1. Assurance du repair café\n2. Budget des ateliers solaires"),
            $L("Present: Camille, Yanis, Léa, Thomas.\n1. Insurance renewed with the same company (95 €/year).\n2. Budget of 300 € approved unanimously.", "Présents : Camille, Yanis, Léa, Thomas.\n1. Assurance renouvelée chez le même assureur (95 €/an).\n2. Budget de 300 € approuvé à l'unanimité."), null],
        [$L('Repair café', 'Repair café'), 'EVENT', 5, '14:00', '18:00', $L('Community hall', 'Maison de quartier'), null, null, 'repair'],
        [$L('Solar cooker workshop #2', 'Atelier cuiseur solaire n°2'), 'WORKSHOP', 12, '10:00', '16:00', $L('Garden courtyard', 'Cour du jardin partagé'),
            $L("Bring: a cardboard box, black paint, gloves.", "À apporter : un carton, de la peinture noire, des gants."), null, 'workshops'],
        [$L('Workshops pole meeting', 'Réunion du pôle ateliers'), 'POLE_MEETING', 2, '18:00', '19:00', $L('Online (Jitsi) or by phone', 'En ligne (Jitsi) ou par téléphone'), null, null, 'workshops'],
    ];
    $e = [];
    foreach ($events as $n => [$title, $type, $offset, $start, $end, $where, $agenda, $minutes, $pole]) {
        $e[$n] = insert('events', ['association_id' => $a, 'title' => $title, 'type' => $type, 'start_at' => $day($offset, $start), 'end_at' => $day($offset, $end),
            'location' => $where, 'agenda' => $agenda, 'minutes' => $minutes, 'pole_id' => $pole ? $p[$pole] : null,
            'project_id' => $n === 3 ? $pr['solar'] : null, 'created_by_id' => $u['ines'], 'created_at' => $ago(20), 'updated_at' => $ago(5)]);
        foreach (['camille' => 'GOING', 'yanis' => 'GOING', 'lea' => 'MAYBE', 'thomas' => 'GOING', 'sarah' => $n === 0 ? 'NOT_GOING' : 'GOING'] as $who => $rsvp) {
            insert('event_attendees', ['event_id' => $e[$n], 'user_id' => $u[$who], 'status' => $rsvp, 'updated_at' => $ago(3)]);
        }
    }

    // ─── Votes ─────────────────────────────────────────────────────────────
    $poll = insert('polls', ['association_id' => $a, 'question' => $L('Buy a second-hand bike repair stand (120 €)?', 'Acheter un pied d\'atelier vélo d\'occasion (120 €) ?'),
        'description' => $L('Found on a classifieds site, in good condition.', 'Trouvé sur un site de petites annonces, en bon état.'), 'closes_at' => $day(7, '23:59'), 'created_by_id' => $u['lucas'], 'created_at' => $ago(2)]);
    $opts = [];
    foreach ([$L('Yes', 'Oui'), $L('No', 'Non'), $L('Abstain', 'Abstention')] as $n => $label) {
        $opts[] = insert('poll_options', ['poll_id' => $poll, 'label' => $label, 'position' => $n]);
    }
    foreach (['yanis' => 0, 'lea' => 0, 'thomas' => 0, 'sarah' => 2, 'ines' => 1] as $who => $o) {
        insert('poll_votes', ['poll_id' => $poll, 'option_id' => $opts[$o], 'user_id' => $u[$who], 'created_at' => $ago(1)]);
    }
    $poll2 = insert('polls', ['association_id' => $a, 'question' => $L('Which day for the winter repair cafés?', 'Quel jour pour les repair cafés d\'hiver ?'), 'multiple' => 1, 'anonymous' => 1,
        'pole_id' => $p['repair'], 'created_by_id' => $u['sarah'], 'closed_at' => $ago(10), 'created_at' => $ago(30)]);
    foreach ([$L('Saturday afternoon', 'Samedi après-midi'), $L('Wednesday evening', 'Mercredi soir'), $L('Sunday morning', 'Dimanche matin')] as $n => $label) {
        $o = insert('poll_options', ['poll_id' => $poll2, 'label' => $label, 'position' => $n]);
        foreach (array_slice(['sarah', 'thomas', 'chloe'], 0, 3 - $n) as $who) {
            insert('poll_votes', ['poll_id' => $poll2, 'option_id' => $o, 'user_id' => $u[$who], 'created_at' => $ago(20)]);
        }
    }

    // ─── Volunteering ──────────────────────────────────────────────────────
    $entries = [['thomas', 240, 'workshops', 'solar', 6], ['lucas', 180, 'workshops', 'solar', 6], ['maxime', 90, 'workshops', 'solar', 6], ['sarah', 300, 'repair', 'repair', 25],
        ['chloe', 240, 'repair', 'repair', 25], ['thomas', 120, 'repair', 'repair', 25], ['yanis', 90, 'treasury', null, 9], ['lea', 150, 'docs', 'wiki', 15],
        ['camille', 120, null, null, 12], ['ines', 60, 'comm', null, 3], ['camille', 90, 'workshops', 'solar', 2]];
    foreach ($entries as [$who, $min, $pole, $proj, $daysAgo]) {
        insert('time_entries', ['association_id' => $a, 'user_id' => $u[$who], 'date' => $day(-$daysAgo), 'minutes' => $min,
            'description' => $proj === 'repair' ? $L('Repair café session', 'Séance du repair café') : ($proj === 'solar' ? $L('Solar cooker workshop', 'Atelier cuiseur solaire') : $L('Association work', 'Travail pour l\'association')),
            'pole_id' => $pole ? $p[$pole] : null, 'project_id' => $proj ? $pr[$proj] : null, 'created_at' => $ago($daysAgo)]);
    }

    // ─── Money ─────────────────────────────────────────────────────────────
    $year = (int) date('Y');
    foreach ([
        [$L('Municipal grant: local initiatives', 'Subvention municipale : initiatives locales'), 'INCOME', 80000, 'GRANT', -60, null, 'TRANSFER'],
        [$L('Donations at the repair café', 'Dons au repair café'), 'INCOME', 6450, 'DONATION', -25, 'repair', 'CASH'],
        [$L('Donations at the repair café', 'Dons au repair café'), 'INCOME', 5200, 'DONATION', -55, 'repair', 'CASH'],
        [$L('Glass panes and black paint', 'Vitres et peinture noire'), 'EXPENSE', 8740, 'SUPPLIES', -8, 'solar', 'CARD'],
        [$L('Second-hand tools', 'Outils d\'occasion'), 'EXPENSE', 6000, 'SUPPLIES', -40, 'repair', 'CASH'],
        [$L('Insurance', 'Assurance'), 'EXPENSE', 9500, 'INSURANCE', -12, null, 'TRANSFER'],
        [$L('Photocopies of posters', 'Photocopies d\'affiches'), 'EXPENSE', 1280, 'COMMUNICATION', -20, null, 'CASH'],
        [$L('Bank fees', 'Frais bancaires'), 'EXPENSE', 360, 'BANK', -30, null, 'OTHER'],
        [$L('Coffee and cakes for the repair café', 'Café et gâteaux du repair café'), 'EXPENSE', 2350, 'FOOD', -25, 'repair', 'CASH'],
        [$L('IBC tank for the rainwater collector', 'Cuve IBC du récupérateur d\'eau'), 'EXPENSE', 9000, 'SUPPLIES', -150, 'rain', 'CARD'],
    ] as [$label, $type, $cents, $cat, $offset, $proj, $method]) {
        insert('transactions', ['association_id' => $a, 'label' => $label, 'type' => $type, 'amount_cents' => $cents, 'category' => $cat, 'date' => $day($offset),
            'payment_method' => $method, 'project_id' => $proj ? $pr[$proj] : null, 'recorded_by_id' => $u['yanis'], 'created_at' => $ago(-$offset), 'updated_at' => $ago(-$offset)]);
    }
    foreach (['camille', 'yanis', 'lea', 'thomas', 'sarah', 'ines'] as $n => $who) {
        $paid = $day(-80 + $n * 7);
        $tx = insert('transactions', ['association_id' => $a, 'label' => enum_label('category', 'MEMBERSHIP') . " $year — {$people[$who][0]}", 'type' => 'INCOME', 'amount_cents' => 1000,
            'category' => 'MEMBERSHIP', 'date' => $paid, 'payment_method' => 'CASH', 'recorded_by_id' => $u['yanis'], 'created_at' => $now, 'updated_at' => $now]);
        insert('dues', ['association_id' => $a, 'user_id' => $u[$who], 'year' => $year, 'amount_cents' => 1000, 'paid_at' => $paid, 'method' => 'CASH', 'transaction_id' => $tx, 'created_at' => $now]);
    }

    // ─── Documents (links only: the demo stays light) ──────────────────────
    foreach ([
        [$L('Statutes (signed)', 'Statuts (signés)'), 'STATUTES', null],
        [$L('Low-tech Lab tutorials', 'Tutoriels du Low-tech Lab'), 'GUIDE', 'https://lowtechlab.org'],
        [$L('Repair Café: start your own', 'Repair Café : lancer le vôtre'), 'GUIDE', 'https://www.repaircafe.org'],
        [$L('Minutes of the founding general assembly', 'Procès-verbal de l\'assemblée générale constitutive'), 'MINUTES', null],
    ] as [$title, $cat, $link]) {
        insert('documents', ['association_id' => $a, 'title' => $title, 'category' => $cat, 'url' => $link ?? 'https://lowtech.example/docs/' . ($cat === 'STATUTES' ? 'statutes.pdf' : 'founding-assembly.pdf'),
            'uploaded_by_id' => $u['lea'], 'created_at' => $ago(100), 'updated_at' => $ago(100)]);
    }

    // ─── Announcement, join code, activity, notifications ──────────────────
    insert('announcements', ['association_id' => $a, 'title' => $L('General assembly in three weeks', 'Assemblée générale dans trois semaines'),
        'body' => $L("Save the date! We will vote the 2027 budget and elect the board.\nIdeas for the agenda are welcome until the 1st.", "Notez la date ! Nous voterons le budget 2027 et élirons le bureau.\nVos idées pour l'ordre du jour sont bienvenues jusqu'au 1er."),
        'pinned' => 1, 'author_id' => $u['camille'], 'created_at' => $ago(1), 'updated_at' => $ago(1)]);
    insert('join_codes', ['association_id' => $a, 'code' => 'MAKER-SPACE', 'label' => $L('Repair café flyers', 'Flyers du repair café'), 'role' => 'MEMBER', 'pole_id' => $p['repair'],
        'max_uses' => 50, 'uses' => 3, 'created_by_id' => $u['camille'], 'created_at' => $ago(10)]);
    foreach ([
        ['thomas', 'completed', 'task', $t[0], $tasks[0][0], 5], ['thomas', 'posted', 'update', $pr['solar'], $projects['solar'][0], 5],
        ['sarah', 'created', 'request', null, $L('Design a poster for the next repair café', 'Créer une affiche pour le prochain repair café'), 1],
        ['lucas', 'created', 'poll', null, $L('Buy a second-hand bike repair stand (120 €)?', 'Acheter un pied d\'atelier vélo d\'occasion (120 €) ?'), 2],
        ['yanis', 'created', 'transaction', null, $L('Glass panes and black paint', 'Vitres et peinture noire'), 8],
        ['camille', 'posted', 'announcement', null, $L('General assembly in three weeks', 'Assemblée générale dans trois semaines'), 1],
    ] as [$who, $action, $type, $id, $label, $daysAgo]) {
        insert('activities', ['association_id' => $a, 'actor_id' => $u[$who], 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'entity_label' => $label, 'created_at' => $ago($daysAgo)]);
    }
    $assocName = (string) val('SELECT name FROM associations WHERE id = ?', [$a]);
    foreach ([
        ['task.comment', ['actor' => 'Thomas Nguyen', 'title' => $tasks[1][0]], "/tasks/{$t[1]}", 'thomas', 1],
        ['poll.new', ['actor' => 'Lucas Bernard', 'title' => $L('Buy a second-hand bike repair stand (120 €)?', 'Acheter un pied d\'atelier vélo d\'occasion (120 €) ?')], '/polls', 'lucas', 2],
        ['member.joined', ['actor' => 'Chloé Laurent', 'association' => $assocName], "/members/{$u['chloe']}", 'chloe', 6],
    ] as [$type, $data, $link, $actor, $daysAgo]) {
        insert('notifications', ['user_id' => $u['camille'], 'association_id' => $a, 'type' => $type, 'data' => json_encode($data + ['association' => $assocName], JSON_UNESCAPED_UNICODE),
            'link' => $link, 'actor_id' => $u[$actor], 'created_at' => $ago($daysAgo)]);
    }

    // ─── A second association: Camille belongs to both ─────────────────────
    $b = insert('associations', ['name' => $L('Community Garden of the Hill', 'Jardin partagé de la Colline'), 'short_name' => $L('Garden', 'Jardin'), 'locale' => $locale,
        'currency' => 'EUR', 'purpose' => $L('Grow vegetables together and compost the neighbourhood\'s waste.', 'Cultiver ensemble et composter les déchets du quartier.'), 'created_at' => $ago(400), 'updated_at' => $ago(400)]);
    insert('members', ['association_id' => $b, 'user_id' => $u['camille'], 'role' => 'MEMBER', 'joined_at' => $day(-380), 'created_at' => $ago(10)]);
    $gardener = insert('users', ['name' => 'Paul Garnier', 'email' => 'paul@garden.example', 'password_hash' => $hash, 'locale' => $locale, 'created_at' => $ago(400), 'updated_at' => $ago(400)]);
    insert('members', ['association_id' => $b, 'user_id' => $gardener, 'role' => 'ADMIN', 'board_position' => 'PRESIDENT', 'joined_at' => $day(-400), 'created_at' => $ago(400)]);
    insert('tasks', ['association_id' => $b, 'title' => $L('Turn the compost', 'Retourner le compost'), 'assignee_id' => $u['camille'], 'due_date' => $day(3), 'creator_id' => $gardener, 'created_at' => $ago(2), 'updated_at' => $ago(2)]);
    insert('events', ['association_id' => $b, 'title' => $L('Seed swap', 'Troc de graines'), 'type' => 'EVENT', 'start_at' => $day(9, '10:00'), 'location' => $L('The garden', 'Au jardin'),
        'created_by_id' => $gardener, 'created_at' => $ago(5), 'updated_at' => $ago(5)]);
});

echo "✔ Demo data loaded.\n\n";
echo "  Sign in:   camille@lowtech.example / " . PASSWORD . " (administrator, member of 2 associations)\n";
echo "  Others:    yanis@, lea@, thomas@, ines@, lucas@, sarah@lowtech.example (same password)\n";
echo "  Join code: MAKER-SPACE (create an account with this code to join the demo association)\n";
