/**
 * @file
 * Invokes the FullCalendar library for each calendar listed in drupalSettings.
 */

(function ($) {
  Drupal.behaviors.fullCalendarSolr = {
    attach: function (context, settings) {
      $(once('fullCalendarSolr', '.views-view-fullcalendar-solr', context)).each(function () {
        if (!drupalSettings.calendars) {
          drupalSettings.calendars = [];
        }
        // Remove existing calendar
        var calendarIndex = parseInt(this.getAttribute('fc-solr-index'));
        if (drupalSettings.calendars[calendarIndex]) {
          drupalSettings.calendars[calendarIndex].destroy();
        }
        // Build calendar options
        var calendarSettings = drupalSettings.FullCalendarSolr[calendarIndex];
        var calendarOptions = {
          ...getPresets(),
          ...calendarSettings['options'],
        };
        calendarOptions['events'] = JSON.parse(calendarSettings['events']);

        // Check if navLinks are enabled
        if (calendarSettings['options']['navLinks']) {
          calendarOptions.navLinkDayClick = function (dateObj, jsEvent) {
            date = formatDate(dateObj);
            var event = drupalSettings.calendars[calendarIndex].getEventById(date);
            if (event && event.url) {
              var resultsPage = event.url + window.location.search;
              window.open(resultsPage);
            }
          };
          calendarOptions.navLinkHint = function (dateText, dateObj) {
            date = formatDate(dateObj);
            var targetEvent = drupalSettings.calendars[calendarIndex].getEventById(date);
            if (!targetEvent || isNaN(targetEvent.extendedProps.count) || targetEvent.extendedProps.count <= 0) {
              return 'No results';
            }
            if (targetEvent.extendedProps.count === 1) {
              return '1 result';
            }
            return targetEvent.extendedProps.count + ' results';
          }
        }

        // Initialize FullCalendar instance
        var calendarEl = $(this).find('.fc-solr-calendar')[0];
        drupalSettings.calendars[calendarIndex] = new FullCalendar.Calendar(
          calendarEl,
          calendarOptions
        );
        drupalSettings.calendars[calendarIndex].render();

        var years = JSON.parse(calendarSettings['years']);
        var selectedYear = '' + drupalSettings.calendars[calendarIndex].getDate().getUTCFullYear();
        // Build custom header with year dropdown
        $(this).find('.fc-solr-header').empty()
          .append(buildHeader(years, selectedYear, calendarSettings['headerText']))
          .change(function () {
            var selectedYear = $('select').val();
            redirectYear(selectedYear);
          });
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
        var headerLabel = '<h3 class="fc-solr-header-label">' + labelTemplate.replaceAll('<year>', selectedYear) + '</h3>';
        var yearSelect = '<select class="fc-solr-year-dropdown">' + yearOptions.join('\n') + '</select>';
        return headerLabel + yearSelect;
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
