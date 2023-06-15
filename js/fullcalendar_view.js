/**
 * @file
 * Invokes the FullCalendar library for each calendar listed in drupalSettings.
 */

(function($) {
  Drupal.behaviors.fullCalendarSolr = {
    attach(context, settings) {
      /**
       * Provides default options for creating a FullCalendar instance
       * @return {object} an object containing FullCalendar options
       */
      function getPresets() {
        return {
          initialView: "multiMonthYear",
          contentHeight: "auto",
          eventDisplay: "background",
          headerToolbar: false,
          dayHeaderFormat: {
            weekday: "narrow" // Single character weekday. E.g. W
          }
        };
      }

      /**
       * Converts a date object into a YYYY-MM-DD string.
       * @param {Date} dateObj a date object
       * @return {string} a date string formatted as YYYY-MM-DD
       */
      function formatDate(dateObj) {
        return dateObj.toLocaleDateString("en-CA", {
          year: "numeric",
          month: "2-digit",
          day: "2-digit"
        });
      }

      /**
       * Builds a custom header for the FullCalendar.
       * @param {Array} years year options to display in the year dropdown
       * @param {string} selectedYear the default year of the dropdown
       * @param {string} headingTemplate template for the heading text
       * @return {string} a string containing HTML
       */
      function buildHeader(years, selectedYear, headingTemplate) {
        // If no selected year and no years with results, don't build the header.
        if (!selectedYear && (!Array.isArray(years) || years.length <= 0)) {
          return "";
        }
        if (!years.includes(selectedYear)) {
          years.push(selectedYear);
          years.sort();
        }
        // Build year dropdown.
        const yearOptions = [];
        years.forEach(year => {
          if (year === selectedYear) {
            yearOptions.push(
              `<option selected value="${year}">${year}</option>`
            );
          } else {
            yearOptions.push(`<option value="${year}">${year}</option>`);
          }
        });
        const heading = `<h2 class="fc-solr-header-label">${headingTemplate.replaceAll(
          "<year>",
          selectedYear
        )}</h2>`;
        const yearSelect = `<select aria-label="Select calendar year" class="fc-solr-year-dropdown">${yearOptions.join(
          "\n"
        )}</select>`;
        return heading + yearSelect;
      }

      /**
       * Redirects the URL to the selected year.
       * @param {string|number} selectedYear the year to redirect to
       */
      function redirectYear(selectedYear) {
        const urlPath = window.location.pathname.split("/");
        const yearIdx = urlPath.lastIndexOf("year");
        if (yearIdx !== -1) {
          urlPath[yearIdx + 1] = selectedYear;
          window.location.pathname = urlPath.join("/");
        }
      }

      $(
        once("fullCalendarSolr", ".views-view-fullcalendar-solr", context)
      ).each(function() {
        const $view = $(this);
        if (!drupalSettings.calendars) {
          drupalSettings.calendars = [];
        }
        // Remove existing calendar.
        const calendarIndex = parseInt($view.attr("fc-solr-index"), 10);
        if (drupalSettings.calendars[calendarIndex]) {
          drupalSettings.calendars[calendarIndex].destroy();
        }

        // Build calendar options.
        const calendarSettings = drupalSettings.FullCalendarSolr[calendarIndex];
        const calendarOptions = {
          ...getPresets(),
          ...calendarSettings.options,
          events: JSON.parse(calendarSettings.events)
        };
        // Initialize FullCalendar instance.
        const calendarEl = $view.find(".fc-solr-calendar")[0];
        drupalSettings.calendars[calendarIndex] = new FullCalendar.Calendar(
          calendarEl,
          calendarOptions
        );

        // Check if navLinks are enabled.
        const calendar = drupalSettings.calendars[calendarIndex];
        if (calendarSettings.options.navLinks) {
          calendar.setOption("navLinkDayClick", function(dateObj, jsEvent) {
            const date = formatDate(dateObj);
            const event = calendar.getEventById(date);
            if (event && event.url) {
              let resultsPage = event.url;
              if (!calendarSettings.directToItem) {
                // If not redirecting to item, need to preserve the search query.
                resultsPage += window.location.search;
              }
              window.open(resultsPage);
            }
          });
          calendar.setOption("navLinkHint", (dateText, dateObj) => {
            const date = formatDate(dateObj);
            const targetEvent = calendar.getEventById(date);
            if (
              !targetEvent ||
              Number.isNaN(targetEvent.extendedProps.count) ||
              targetEvent.extendedProps.count <= 0
            ) {
              return `No results for ${dateText}`;
            }
            if (targetEvent.extendedProps.count === 1) {
              return `1 result for ${dateText}`;
            }
            return `${targetEvent.extendedProps.count} results for ${dateText}`;
          });
        }

        calendar.render();

        // Process day cells. This must be done after the calendar is rendered.
        $view.find("td.fc-day.fc-daygrid-day").each(function() {
          // In FullCalendar V6, disabled grid cells in the MultiMonthYear view
          // have broken ARIA references. This is a workaround for now.
          const refId = $(this).attr("aria-labelledby");
          const $ref = $(`#${refId}`, $(this));
          if (!$ref[0]) {
            $(this).removeAttr("aria-labelledby");
          } else if (!$(this).find(".fc-event.fc-bg-event")[0]) {
            // Remove tab focus from dates without results.
            $ref.attr("tabindex", "-1");
            $ref.removeAttr("data-navlink");
          } else {
            // Modify aria-labels for dates with results.
            const title = $ref.attr("title");
            $(this).removeAttr("aria-labelledby");
            $(this).attr("aria-label", `Go to ${title}`);
          }
        });

        const years = JSON.parse(calendarSettings.years);
        const calendarYear = `${calendar.getDate().getUTCFullYear()}`;
        const headerHtml = buildHeader(
          years,
          calendarYear,
          calendarSettings.headingText
        );

        // Build custom header with year dropdown
        $view
          .find(".fc-solr-header")
          .empty()
          .append(headerHtml)
          .change(function() {
            const selectedYear = $("select", this).val();
            redirectYear(selectedYear);
          });
      });
    }
  };
})(jQuery);
