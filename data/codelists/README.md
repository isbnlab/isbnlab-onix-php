# ONIX for Books code lists

`ONIX_BookProduct_Codelists_Issue_*.json` is EDItEUR's ONIX for Books code lists file,
exactly as downloaded from <https://www.editeur.org/14/Code-Lists/>, with no changes.

Copyright © EDItEUR Limited (<https://www.editeur.org/>). It is used under the
Licence to use EDItEUR Standards (<https://doi.org/10.4400/nwgj>). The MIT License
for the rest of this package does not cover this file.

This package is not affiliated with or endorsed by EDItEUR.

## Updating

EDItEUR publishes a new issue about four times a year and announces it on the ONIX
mailing list (send a blank email to onix+subscribe@groups.io to join). EDItEUR asks
that a new issue is copied only a week or two after release, in case it needs
correcting, so the script below refuses an issue less than 14 days old unless given
`--now`. From a clone of this repository, run it, then run the tests, commit and tag a
patch release:

```bash
bin/fetch-codelists
```
