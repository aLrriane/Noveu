# NoveU (PHP version) - code backup

A personal novel-reading web app written in PHP + MySQL: accounts, novel and
chapter management, EPUB chapter extraction, search, rankings, reviews, lists.

This repository is the **code backup only**. GitHub Pages cannot run PHP, so
the page at the Pages link is just a static preview (`index.html`).

## Folder layout

```
Noveu/
|-- index.html        static preview page for GitHub Pages
|-- database/
|   `-- schema.sql    database structure only (no user data)
`-- php-app/          the whole PHP app (pages, classes, includes, assets)
    |-- config/       db.php + *.example.php templates (no real passwords)
    |-- classes/      EpubParser, Sorter, search tree, linked list
    |-- includes/     header, footer, navbar, card templates
    |-- libs/         PHPMailer
    |-- assets/       css, js, images
    `-- uploads/      empty here; avatars, chapters, covers, epubs go here
```

## Run it locally (XAMPP)

1. Copy the `php-app` folder to `C:\xampp\htdocs\noveu`.
2. Start Apache and MySQL in XAMPP.
3. In phpMyAdmin create a database named `noveu` and import `database/schema.sql`.
4. Open `http://localhost/noveu/`.

Local database settings are in `php-app/config/db.php` (user `root`, no password,
ports 3307 then 3306).

## Email features (forgot password, verification)

Copy `php-app/config/secrets.example.php` to `secrets.php` and add a Gmail
address and a Gmail App Password. `secrets.php` is git-ignored and must never be
committed.

## Where the data lives

Novels, chapters, covers, epubs, avatars and the full database export are **not**
in this repo. They are kept in a separate private location.
