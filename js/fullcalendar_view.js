/**
 * @file
 * Invokes the FullCalendar library for each calendar listed in drupalSettings.
 */

(function ($) {
  Drupal.behaviors.fullCalendarSolr = {
    attach: function (context, settings) {
      $(once('fullCalendarSolr', '.views-view-fullcalendar-solr', context)).each(function () {
        var $view = $(this);
        if (!drupalSettings.calendars) {
          drupalSettings.calendars = [];
        }
        // Remove existing calendar.
        var calendarIndex = parseInt($view.attr('fc-solr-index'));
        if (drupalSettings.calendars[calendarIndex]) {
          drupalSettings.calendars[calendarIndex].destroy();
        }

        // Build calendar options.
        var calendarSettings = drupalSettings.FullCalendarSolr[calendarIndex];
        var calendarOptions = {
          ...getPresets(),
          ...calendarSettings['options'],
          events: JSON.parse(calendarSettings['events']),
        };
        // Initialize FullCalendar instance.
        var calendarEl = $view.find('.fc-solr-calendar')[0];
        drupalSettings.calendars[calendarIndex] = new FullCalendar.Calendar(calendarEl, calendarOptions);

        // Check if navLinks are enabled.
        var calendar = drupalSettings.calendars[calendarIndex];
        if (calendarSettings['options']['navLinks']) {
          calendar.setOption('navLinkDayClick', navLinkDayClick(calendar));
          calendar.setOption('navLinkHint', navLinkHint(calendar));
        }
        calendar.render();

        // Process day cells.
        $view.find('td.fc-day.fc-daygrid-day')
          .each(function () {
            // In FullCalendar V6, disabled grid cells in the MultiMonthYear view
            // have broken ARIA references. This is a workaround for now.
            var refId = $(this).attr('aria-labelledby');
            var $ref = $('#' + refId, $(this));
            if (!$ref[0]) {
              $(this).removeAttr('aria-labelledby');
            }
            else if (!$(this).find('.fc-event.fc-bg-event')[0]) {
              // Remove tab focus from dates without results.
              $ref.attr('tabindex', '-1');
              $ref.removeAttr('data-navlink');
            }
            else {
              // Modify aria-labels for dates with results.
              var title = $ref.attr('title');
              $(this).removeAttr('aria-labelledby');
              $(this).attr('aria-label', 'Go to ' + title);
            }
          });

        var years = JSON.parse(calendarSettings['years']);
        var calendarYear = '' + calendar.getDate().getUTCFullYear();
        var headerHtml = buildHeader(years, calendarYear, calendarSettings['headerText']);
        // Build custom header with year dropdown
        $view.find('.fc-solr-header')
          .empty()
          .append(headerHtml)
          .change(() => redirectYear($('select', this).val()));
      });

      /**
       * Provides default options for creating a FullCalendar instance
       * @returns an object containing FullCalendar options
       */
      function getPresets() {
        var presetOptions = {
          initialView: 'multiMonthYear',
          contentHeight: 'auto',
          eventDisplay: 'background',
          headerToolbar: false,
          dayHeaderFormat: {
            weekday: 'narrow', // Single character weekday. E.g. W
          },
        };
        return presetOptions;
      }

      /**
       * Converts a date object into a YYYY-MM-DD string.
       * @param {Date} dateObj a date object
       * @returns a date string formatted as YYYY-MM-DD
       */
      function formatDate(dateObj) {
        date = dateObj.toLocaleDateString('en-CA', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
        });
        return date;
      }

      /**
       * Builds a custom header for the FullCalendar.
       * @param {Array} years year options to display in the year dropdown
       * @param {string} selectedYear the default year of the dropdown
       * @param {string} labelTemplate template for the header text
       * @returns a string containing HTML
       */
      function buildHeader(years, selectedYear, labelTemplate) {
        // If no selected year and no years with results, don't build the header.
        if (!selectedYear && (!Array.isArray(years) || years.length <= 0)) {
          return '';
        }
        if (!years.includes(selectedYear)) {
          years.push(selectedYear);
          years.sort();
        }
        // Build year dropdown.
        var yearOptions = [];
        years.forEach((year) => {
          if (year === selectedYear) {
            yearOptions.push('<option selected value="' + year + '">' + year + '</option>');
          }
          else {
            yearOptions.push('<option value="' + year + '">' + year + '</option>');
          }
        });
        var headerLabel = '<h2 class="fc-solr-header-label">' + labelTemplate.replaceAll('<year>', selectedYear) + '</h2>';
        var yearSelect = '<select aria-label="Select calendar year" class="fc-solr-year-dropdown">' + yearOptions.join('\n') + '</select>';
        return headerLabel + yearSelect;
      }

      /**
       * Generates navLinkDayClick callback.
       * @param {object} calendar the calendar instance
       */
      function navLinkDayClick(calendar) {
        return (dateObj, jsEvent) => {
          date = formatDate(dateObj);
          var event = calendar.getEventById(date);
          if (event && event.url) {
            var resultsPage = event.url + window.location.search;
            window.open(resultsPage);
          }
        };
      }

      /**
       * Generates the navLinkHint callback.
       * @param {object} calendar the calendar instance
       */
      function navLinkHint(calendar) {
        return (dateText, dateObj) => {
          date = formatDate(dateObj);
          var targetEvent = calendar.getEventById(date);
          if (!targetEvent || isNaN(targetEvent.extendedProps.count) || targetEvent.extendedProps.count <= 0) {
            return 'No results for ' + dateText;
          }
          if (targetEvent.extendedProps.count === 1) {
            return '1 result for ' + dateText;
          }
          return targetEvent.extendedProps.count + ' results for ' + dateText;
        }
      }

      /**
       * Redirects the URL to the selected year.
       * @param {string|number} selectedYear
       */
      function redirectYear(selectedYear) {
        var urlPath = window.location.pathname.split('/');
        var yearIdx = urlPath.lastIndexOf('year');
        if (yearIdx !== -1) {
          urlPath[yearIdx + 1] = selectedYear;
          window.location.pathname = urlPath.join('/');
        }
      }
    },
  };
})(jQuery);
