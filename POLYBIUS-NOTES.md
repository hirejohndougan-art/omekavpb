# Notes for Polybius (from the Visual Page Builder work)

John's rule (Oct 2026): **Visual Page Builder is independent of Polybius.**
Design and build the module as if Polybius doesn't exist. Anything that would
be better done in Polybius goes on this list instead, to be built in a
Polybius session (bring the Polybius transfer kit).

## To build in Polybius

1. **Clean, sortable dates on export to Omeka.** Alongside the original date
   text ("circa 1920", "1920s", "June 14, 1911"), also send a sortable value
   (ISO date or year) so Omeka-side filtering by date range works well.
2. **Replace a rescanned file in place when syncing**, instead of deleting the
   media and adding a new one (which changes its identity and breaks page
   blocks and links). Depends on an Omeka-side "replace file" ability.
3. **Check for existing names and subjects before adding them** during sync,
   to avoid near-duplicates ("J. Smith" / "Smith, John").

## Module side (independent; may be built without Polybius)

- Faceted search on the public site, working from whatever dates and values
  are already in Omeka (loose date reading like the Timeline block's).
- "Replace file" for media in Omeka's admin: decided to be its own separate
  module ("Replace File"), on the to-do list for later.
- A lookup/matching service for other tools (e.g. OpenRefine).
