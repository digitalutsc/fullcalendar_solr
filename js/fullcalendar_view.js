/**
 * @file
 * Invokes the FullCalendar library for each calendar listed in drupalSettings.
 */

(function ($) {
  Drupal.behaviors.fullCalendarSolr = {
    attach: function (context, settings) {
      $('.views-view-fullcalendar-solr').once('fullCalendarSolr').each(function () {
        var calendarIndex = parseInt(this.getAttribute('fc-solr-index'));
        if (!drupalSettings.calendars) {
          drupalSettings.calendars = [];
        }
        // Remove existing calendar
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
            // Convert to YYYY-MM-DD format
            date = formatDate(dateObj);
            if (drupalSettings.calendars[calendarIndex].getEventById(date)) {
              window.location.pathname = calendarSettings['navLinkDay'] + '/' + date;
            }
          };
          calendarOptions.navLinkHint = function (dateText, dateObj) {
            // Convert to YYYY-MM-DD format
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
        drupalSettings.calendars[calendarIndex].gotoDate(getUrlYear());

        // Build custom header with year dropdown
        $(this).find('.fc-solr-header').empty()
          .append(buildHeader(JSON.parse(calendarSettings['years']), getUrlYear()))
          .change(function () {
            var selectedYear = $('select').val();
            redirectYear(selectedYear);
          });
      });

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
       * Converts a date object into a YYYY-MM-DD string
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

      function buildHeader(years, selectedYear, labelTemplate = 'All issues for {year}') {
        var headerLabel = '<h3 class="fc-solr-header-label">' + labelTemplate.replace('{year}', selectedYear) + '</h3>';
        // Build year dropdown
        var yearOptions = [];
        if (!Array.isArray(years) || years.length <= 0) {
          yearOptions.push('<option selected value="">No Results</option>');
        }
        else {
          years.forEach((year) => {
            if (year == selectedYear) {
              yearOptions.push('<option selected value="' + year + '">' + year + '</option>');
            }
            else {
              yearOptions.push('<option value="' + year + '">' + year + '</option>');
            }
          });
        }
        var yearSelect = '<select class="fc-solr-year-dropdown">' + yearOptions.join('\n') + '</select>';
        return headerLabel + yearSelect;
      }

      function redirectYear(selectedYear) {
        var urlPath = window.location.pathname.split('/');
        urlPath[urlPath.length - 1] = selectedYear;
        window.location.pathname = urlPath.join('/');
      }

      function getUrlYear() {
        var urlPath = window.location.pathname.split('/');
        return urlPath[urlPath.length - 1];
      }
    },
  };
})(jQuery);
