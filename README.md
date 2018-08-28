# Mapping American Religious Ecologies

This Omeka S module enables functionality needed for the Mapping American
Religious Ecologies project: http://religiousecologies.org

## Initial Data Model and Import

This section covers the initial, Omeka-oriented (as opposed to transcription)
data model. The implementation of the MARE vocabulary and the Omeka item sets
and resource templates are in the [Mare-module GitHub repository](https://github.com/chnm/Mare-module).

Future scripts will need to make resource linkages between schedules and their
denominations and counties using their respective identifiers. This is possible
because mare:scheduleId, mare:denominationId, and mare:countyId are guaranteed
to be unique.

### Vocabulary

Classes and properties in the MARE namespace (http://religiousecologies.org/vocab#):

#### Classes
- mare:Schedule: A schedule from the 1926 U.S. Census of Religious Bodies.
- mare:Denomination: A religious denomination.
- mare:County: A United States county.

#### Properties:
- mare:denomination:  The denomination of this resource.
- mare:county: The county of this resource.
- mare:scheduleId: A legacy schedule identifier by the 1926 U.S. Census of Religious Bodies.
- mare:denominationId: A legacy denomination identifier by the 1926 U.S. Census of Religious Bodies.
- mare:countyId: A county identifier by the Atlas of Historical County Boundaries.
- mare:countyName: A county name.
- mare:denominationFamily: A supergroup of a denomination.
- mare:digitized: A date of the digitization of a resource.
- mare:box: A box identifier.
- mare:stateTerritory: A state/territory of a resource.
- mare:fips: A FIPS county code.

### Resource Templates

Omeka resource templates used by the MARE project:

#### Schedule
- Class: mare:Schedule
- Properties:
  - dcterms:title (literal)
  - dcterms:creator (literal)
  - dcterms:source (uri)
  - mare:box (literal)
  - mare:scheduleId (literal)
  - mare:denomination (resource:item)
  - mare:denominationId (literal)
  - mare:county (resource:item)
  - mare:countyId (literal)
  - mare:digitized (literal)
 
#### Denomination
 - Class: mare:Denomination
 - Properties:
   - dcterms:title (literal)
   - dcterms:description (literal)
   - mare:denominationId (literal)
   - mare:denominationFamily (literal)

#### County
- Class: mare:County
- Properties:
  - dcterms:title (literal)
  - mare:countyId (literal)
  - mare:countyName (literal)
  - mare:fips (literal)
  - mare:stateTerritory (literal)
  - dcterms:type (literal)
  - dcterms:source (uri)

### CSV Import Mapping

Mapping instructions for batch importing from prepared CSV files to Omeka S,
using the CSVImport module.

The FileSideload module must be installed and configured, the MARE vocabulary
must be imported, and the MARE item sets and resource templates must be created.
Use the `prefix:local_name` format as CSV column headers to enable CSVImport
auto-mapping.

#### Schedules
- Import type: Item
- Resource template: Schedule
- Class: mare:Schedule
- Item set: Schedules
- Mapping:
  - relative_path_to_image [Media import: Sideload]
  - dcterms:title
  - mare:scheduleId
  - mare:denominationId
  - mare:countyId
  - dcterms:creator "1926 U.S. Census of Religious Bodies"
  - dcterms:source [Properties: Import as URL reference] https://catalog.archives.gov/id/2791163
  - mare:box
  - mare:digitized

#### Denominations
- Import type: Item
- Resource template: Denomination
- Class: mare:Denomination
- Item set: Denominations
- Mapping:
  - dcterms:title
  - mare:denominationId
  - mare:denominationFamily

#### Counties
- Import type: Item
- Resource template: County
- Class: mare:County
- Item set: Counties
- Mapping:
  - dcterms:title
  - mare:countyId 
  - mare:countyName
  - mare:fips
  - mare:stateTerritory
  - dcterms:type
  - dcterms:source [Properties: Import as URL reference] https://publications.newberry.org/ahcbp/
