# ONIX for Books code lists

`ONIX_BookProduct_Codelists_Issue_*.json` is EDItEUR's ONIX for Books code lists file,
exactly as downloaded from <https://www.editeur.org/14/Code-Lists/>, with no changes.

Copyright © EDItEUR Limited (<https://www.editeur.org/>). It is used under the
Licence to use EDItEUR Standards (<https://doi.org/10.4400/nwgj>). The MIT License
for the rest of this package does not cover this file.

This package is not affiliated with or endorsed by EDItEUR.

## Updating

EDItEUR publishes a new issue about four times a year. To replace the file with the
newest issue, run the following from a clone of this repository, then run the tests
and commit:

```bash
bin/fetch-codelists
```
