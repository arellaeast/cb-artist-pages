# CB Artist Pages

WordPress plugin for captainbaltimore.com — Radio-Deck-styled templates for
Pirate Radio artist profiles (`cb_artist` posts at `/radio/artists/<slug>/`).

Single layout (top to bottom): Title → circular artist photo → Bio → Baltimore
Connection → Top Tracks → Discography.

## Archive hub (v1.2.0+)

`/radio/artists/` renders the plugin's own archive template (before v1.2.0 it
fell through to the theme's blog listing — news category filter bar, no
photos). Same deck styling: CAPT·BLTMR masthead, "Pirate Radio — The Artists"
header, then a responsive card grid. Each card: circular photo (yellow
pirate-flag fallback), name, neighborhood chip and home-station chip (the
artist's most-voted live track's channel + dial) when present, and a two-line
bio blurb; the whole card links to the profile. The archive query is sorted
A–Z by artist name (it's a directory) at 24 per page with deck-styled
pagination.

## Enrichment fields

| Section | Source |
| --- | --- |
| Title | Post title |
| Photo | Featured image (rendered as a circle; falls back to the yellow pirate-flag art) |
| Bio | Post content — target ~250 words |
| Baltimore Connection | `cb_connection` meta (narrative); `cb_hood` meta shows as a location chip and as a fallback line |
| Links | `cb_links` meta — JSON array of URLs, rendered as labeled chips |
| Top Tracks | Automatic — the artist's live tracks from `cb_radio_submissions`, most-voted first, with station name + dial |
| Discography | `cb_discography` meta — one release per line: `Title \| Year \| Note or URL` |

Sections with no data are hidden. An artist with no data at all gets a
"Full profile coming soon" note. Top Tracks needs no enrichment — it is
driven by the radio submissions data.

## Editing (v1.1.0+)

Every field is fillable from the artist edit screen by anyone who can edit
the post (Editors included): bio in the main editor, photo via Featured
Image, and an **Artist Profile Details** meta box with neighborhood,
photo URL (sideloaded into the featured image on save), Baltimore
connection, links (one URL per line), and discography.

## Deploy

Fresh folder `cb-artist-pages` in `wp-content/plugins/`, owned by www-data
(dirs 755, files 644), activate, purge the nginx fastcgi cache, verify
through the web.
