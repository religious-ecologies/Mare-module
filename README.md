# Mapping American Religious Ecologies

This Omeka S module enables functionality needed for the Mapping American
Religious Ecologies project: http://religiousecologies.org

## Vocabulary

See the [MARE N3 file](https://github.com/chnm/Mare-module/blob/master/vocabs/mare.n3)
for the most up-to-date descriptions of classes and properties in the MARE
namespace.

## The 1926 U.S. Census of Religious Bodies

### CSV Import Mapping

Mapping instructions for batch importing from prepared CSV files to Omeka S,
using the CSVImport module.

The FileSideload module must be installed and configured, and the MARE module
must be installed (the module creates the MARE vocabulary, item sets, and
resource templates). Use the `prefix:local_name` format as CSV column headers to
enable CSVImport auto-mapping.

Future scripts will need to make resource linkages between schedules and their
denominations and counties using their respective identifiers. This is possible
because mare:scheduleId, mare:denominationId, and mare:ahcbCountyId are
guaranteed to be unique.

#### Schedules
- Basic import settings:
  - Import type: Item
  - Resource template: Schedule (1926)
  - Class: mare:Schedule
  - Item sets:
    - Schedules
    - 1926 U.S. Census of Religious Bodies
  - Owner: Lincoln M.
- Map to Omeka S data:
  - relative_path_to_image [Media import: Sideload]
  - dcterms:title
  - mare:scheduleId
  - mare:denominationId
  - mare:ahcbCountyId
  - dcterms:creator "1926 U.S. Census of Religious Bodies"
  - dcterms:source [Properties: Import as URL reference] https://catalog.archives.gov/id/2791163
  - mare:box
  - mare:digitized
  - mare:digitizedBy
  - mare:catalogedBy
  - mare:imageRecheck

#### Denominations
- Basic import settings:
  - Import type: Item
  - Resource template: Denomination
  - Class: mare:Denomination
  - Item set: Denominations
  - Owner: Lincoln M.
- Map to Omeka S data:
  - dcterms:title
  - mare:denominationId
  - mare:denominationFamily

#### Counties
- Basic import settings:
  - Import type: Item
  - Resource template: County
  - Class: mare:County
  - Item set: Counties
  - Owner: Lincoln M.
- Map to Omeka S data:
  - dcterms:title
  - mare:ahcbCountyId
  - mare:countyName
  - mare:fipsCountyCode
  - mare:stateTerritory
  - dcterms:type
  - dcterms:source [Properties: Import as URL reference] https://publications.newberry.org/ahcbp/

## Religious Space and Immigration in the Nation's Capital
