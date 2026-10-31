# CB Artist Pages

WordPress plugin for captainbaltimore.com — a Radio-Deck-styled single template
for Pirate Radio artist profiles (`cb_artist` posts at `/radio/artists/<slug>/`).

Layout (top to bottom): Title → circular artist photo → Bio → Baltimore
Connection → Top Tracks → Discography.

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

## Deploy

Fresh folder `cb-artist-pages` in `wp-content/plugins/`, owned by www-data
(dirs 755, files 644), activate, purge the nginx fastcgi cache, verify
through the web.
