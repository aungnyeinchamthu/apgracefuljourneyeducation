# apgracefuljourneyeducation

Deployed automatically to Hostinger on push to `main`.

The download uses `documents/University_List.xlsx`. Replace this file to update
the download. The website displays the original curated partner list by default.

The expanded workbook-driven version is retained for client review. To preview
it, set `$useExpandedUniversityList = true` in `index.php`; reset it to `false`
to restore the curated section. The preview reads each country worksheet's
numbered rows under the `Provider` heading, skipping the `University List`
summary worksheet. Keep the row numbers in column A. The preview requires PHP
ZIP and SimpleXML extensions.
