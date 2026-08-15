# Level Up Embed (WordPress plugin)

The landing page stays in this repo and is published as a static page.
WordPress embeds it and collects the signups.

```
level-up-embed/          the plugin
build-zip.ps1            builds dist/level-up-embed.zip for uploading
```

## Build the ZIP

```powershell
pwsh ./wordpress/build-zip.ps1
```

Produces `wordpress/dist/level-up-embed.zip`. Upload it in WordPress under
**Plugins → Add New → Upload Plugin**, activate, then open **Settings → Level Up**.

## Settings

### The embedded page

| Setting | What it does |
|---|---|
| **Page URL** | Where the Level Up page is published. Must be https if the site is https, or the browser blocks the frame. |
| **Layout** | The three variants below. |
| **Show it on** | Pick an existing page, or use **Create a page for it** to make one. Leave unset to place it yourself with `[level_up_embed]`. |
| **Starting height** | Only used for the moment before the page reports its real height. |

### Creating the page

At the bottom of the settings screen, **Create a page for it** takes a title and
a URL slug, creates a published page and selects it. The page is left empty on
purpose — the plugin renders the embed onto the chosen page automatically, so
there is nothing to paste in. If the slug is already taken it says so rather
than quietly making a second page at `level-up-2`.

### Layout variants

1. **Inside the site** — the theme's header and navigation wrap the embed, and
   Level Up reads as a page of the TYC site. The embedded page's own sticky
   header is hidden, because two stacked navigations look broken.
2. **Standalone, keeping the page's own section links** — no theme chrome, but
   the embedded page keeps its jump links (The Event, Speakers, Run Sheet,
   Crew, Sponsors), which are otherwise the only navigation on a long page.
3. **Standalone, no navigation at all** — nothing but the page.

Both standalone options are served through a template inside the plugin, so
nothing has to be copied into the theme and a theme update cannot remove it.
They need a page selected under "Show it on" — that is how the plugin knows
which page to strip the theme from.

**Recommended: option 1.** Someone arriving from social should be able to reach
the rest of the chamber's site. Options 2 and 3 give them no way out of the
page, which suits a paid-campaign landing page and little else.

### Signups

Addresses are stored in WordPress. There is no third-party account and no API
key anywhere in the plugin.

- **Settings → Level Up Signups** lists them, with search and deletion.
- **Export CSV** on the settings screen gives you `email,signed_up_utc` to
  import into whatever you send from.
- **Email me whenever someone signs up** is an optional notification. Signups
  are stored either way, so a mail failure never loses an address.

Only the address and the date are stored — no IP address, no user agent. The IP
is used briefly as a rate-limit key and never written down.

**Who collects them** also offers "the embedded page handles its own signups"
(the iframe shows its own form and WordPress captures nothing, which needs
`SUBSCRIBE_ENDPOINT` set in `index.html`) and "no signup form anywhere".

### Getting addresses into a mailing tool

Export the CSV and import it. Nothing syncs automatically, which is the point:
there is no API key to expire, no provider API to change under you, and no
silent failure mode where signups vanish into a misconfigured integration. The
cost is a manual export before each send — a couple of minutes, against a
monthly email.

`worker/` in this repo is from an earlier approach that pushed signups straight
to Mailjet from a static page. It is not used by this plugin.

## How the iframe gets its height

An iframe has a fixed height and cannot size itself to its content, which is why
embeds normally end up as a short box with an inner scrollbar. The page measures
itself and posts its height to the parent, which resizes the frame. It re-measures
when the content changes, so opening an FAQ item grows the frame.

The parent only accepts messages whose origin matches the iframe's exactly and
which came from that frame, so another site cannot resize it or drive scrolling.

If JavaScript is blocked the frame stays at the starting height and scrolls
internally — degraded but usable, and the signup form still submits.

## Checking it works

- The frame should have **no inner scrollbar** and no large gap beneath it.
- Clicking **GET UPDATES** in the iframe should scroll down to the signup form.
- A bad address should turn the field red without a network request.
- A good one should appear under **Settings → Level Up Signups**.
- Submitting the same address twice should not create a duplicate.

## Editing the plugin

Source is in `level-up-embed/`. After changing anything, rebuild the ZIP.

| File | Contains |
|---|---|
| `level-up-embed.php` | Plugin header, constants, includes |
| `includes/options.php` | Defaults, stored settings, derived values |
| `includes/settings.php` | The settings screen, page creation |
| `includes/signups.php` | Signup storage, admin list, CSV export |
| `includes/frontend.php` | Shortcode, auto-render, assets, template override |
| `includes/rest.php` | The signup endpoint |
| `assets/embed.css` | Embed and form styling |
| `assets/embed.js` | Iframe resizing and form submission |
| `templates/standalone.php` | Bare page template |
