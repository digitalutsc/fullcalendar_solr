# FullCalendar Solr


## Table of contents

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Creating a Year View](#creating-a-year-view)
  - [Creating a Day View](#creating-a-day-view)
  - [Redirecting to a Single Result](#redirecting-to-a-single-result)
- [Troubleshooting](#troubleshooting)


## Introduction

FullCalendar Solr provides integration with the
[FullCalendar](https://fullcalendar.io/) JavaScript library
to provide a Year Calendar View display formatter that is compatible with
Search API.

The calendar highlights dates associated with content. It also features a year
dropdown containing only years with results. This module is compatible
with the Search API Solr and Facets modules.

**Note:** This formatter is not compatible with regular content Views.

![image](docs/year-calendar.png)


## Requirements

This module requires the following modules:

- [Search API](https://www.drupal.org/project/search_api)
- [Views](https://www.drupal.org/project/views)

The Search API backend needs to support the `search_api_facets` option.


## Installation

1. Clone this repo into `drupal/web/modules/contrib` or install using Composer.
1. Enable the module at `Admin > Extend` or use Drush.


## Configuration


### Creating a Year View

1. At `/admin/structure/views`, click `Add view`. Under `View settings > Show`,
select an index.
1. Create a Page. Set the display format to `FullCalendar Solr`.
1. Configure the page path such that the last component is 'year'.
(e.g. `/a/b/c/year`)
1. Under `Fields`, add a string field containing a date in YYYY-MM-DD format.
Any dates not in YYYY-MM-DD format will not be displayed in the calendar.
1. Under `Advanced > Contextual Filters`, select an argument containing year
values in YYYY format.
1. Under `Format > FullCalendar Solr Settings`, configure the date and year
fields.
1. Add any additional view configurations as needed.
1. Save the view.


### Creating a Day View

The year calendar can be configured to redirect to a day view when a
highlighted date is clicked.

1. Edit the year view page. Under `Format > FullCalendar Solr Settings`, check
the `Navigation Links to Day View` option and save.
1. Click `Add > Page`. This will be the new day view.
1. Select a display style. (One that is not `FullCalendar Solr`)
1. Under `Advanced > Contextual Filter`, add a field containing a string date
in YYYY-MM-DD format. This should be the same as the date field used in the
year view.
    - If the date field is not available, try adding the field to the Search
    API Index.
1. Configure the page path. The path of this view should be the same as the
path of the year view except the last URL component is 'day' instead of 'year'
(i.e. if the year view has path `/a/b/c/year`, the day view must have path
`/a/b/c/day`).
**Note:** The year and day view should have the same contextual filters.
1. Add any additional view configurations as needed.
1. Save the view.


### Redirecting to a Single Result

If a highlighted date has only one result, the year calendar can redirect to
the result itself instead of the day view.

1. __Prerequisites:__ Navigation Links are enabled and a day view is set up.
(See [Creating a Day View](#creating-a-day-view))
1. Edit the year view page.
1. Under `Fields`, add a field containing an item path or URL.
1. Under `Format > FullCalendar Solr Settings`, check the `Link to Item`
option.
1. The `Item URL Field` dropdown should now be available. Select the field
containing the item path or URL.
1. Save the formatter settings.
1. Save the view.


## Troubleshooting

If the year calendar doesn't display all dates with content, go to
`View settings > Pager options`, and set the `Items per page` option to 365.
