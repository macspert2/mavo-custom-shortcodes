# Mavo Custom Shortcodes

## `[mavo_hub_strip]`

A compact "read also" strip linking a post to the hub pages that own it. Since
`mavo-hub-manager` introduced the hub hierarchy, a post has up to two primary hubs — one
**geographic** and one **thematic** — and the strip can link to either or both without
being told a slug.

### Sentence mode

The editor writes the sentence; each `{…}` marker becomes a link.

```
[mavo_hub_strip text="Voir aussi notre {geo:guide de la France en famille} et nos {theme:idées de city trips}."]
[mavo_hub_strip text="Retrouvez notre {geo:guide de la France en famille}."]
[mavo_hub_strip slug="france" text="Retrouvez aussi notre {guide France en famille}."]
```

| Marker | Links to |
|---|---|
| `{geo:anchor}` | the post's primary **geographic** hub |
| `{theme:anchor}` | the post's primary **thematic** hub |
| `{anchor}` | the `slug` attribute — the original behaviour |

Only the literal prefixes `geo:` and `theme:` are special, so an anchor like
`{Paris : la ville}` is left alone. Any number of markers is allowed, in any order.

### List mode

Leave `text` out and the strip lists the hub titles instead of prose — droppable into a
template, or pasteable in bulk from the audit's *no link back* report.

```
[mavo_hub_strip]                both hubs, whichever exist
[mavo_hub_strip hub="geo"]      geographic only
[mavo_hub_strip hub="theme"]    thematic only
```

`hub` applies to list mode only; in sentence mode the markers decide. `slug` needs a
`text`, since without prose there is no anchor to give it.

### Attributes

| Attribute | Default | Meaning |
|---|---|---|
| `text` | — | The sentence, with one or more `{…}` markers. Omit for list mode. |
| `slug` | — | Explicit target for unnamed markers. Wins over the hub hierarchy. Internal paths and same-host URLs only. |
| `hub` | `both` | `geo`, `theme` or `both`. List mode only. |
| `label` | by language | Overrides the strip label (`À lire aussi` / `Read also` / `Auch lesenswert`, via Polylang). |

### When something is missing

Nothing is ever shown broken, and visitors never see an error.

* **Sentence mode, a marker cannot be resolved** — the post has no hub of that type, the
  hub is not published, or the hub *is* this post — the whole strip is suppressed. Half a
  sentence (`Voir aussi X et`) reads worse than no strip.
* **List mode** — missing hubs are skipped; if neither exists the strip disappears.
* Users who can edit posts get an HTML comment naming the problem in both cases.

### Relationship to `mavo-hub-manager`

Hubs are read through `mavo_get_primary_hub()`, never through raw meta; two meta reads per
render, so no caching. If the plugin is inactive, hub markers report an admin error and the
strip is suppressed — `slug` strips keep working.

That plugin's audit understands this shortcode as a link back, including the slugless
forms, and its *no link back* report suggests a ready-to-paste
`[mavo_hub_strip text="{geo:…}"]`. The two must be updated together: the audit reads
`hub` and the `{geo:…}` / `{theme:…}` markers as text, without rendering.

## Tests

No WordPress required; the harness stubs what the shortcode calls, including
`mavo_get_primary_hub()`.

```sh
./tests/run.sh
```

| File | Covers |
|---|---|
| `test-hub-strip.php` | both modes, marker binding, escaping, unresolvable markers, admin errors, the draft/self-link guards |
| `test-no-hub-manager.php` | the shortcode with Mavo Hub Manager inactive |
