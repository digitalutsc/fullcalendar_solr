/**
 * @file
 * Invokes the FullCalendar library for each calendar listed in drupalSettings.
 */

(function ($) {
  Drupal.behaviors.fullCalendarSolr = {
    attach: function (context, settings) {
      drupalSettings.FullCalendarSolr.forEach(function (calendar, key) {
        if (calendar["init"] != true) {
          var calendarOptions = {
            ...getPresets(),
            ...calendar["options"],
          };

          if (calendar["options"]["navLinks"]) {
            calendarOptions.navLinkDayClick = function (dateObj, jsEvent) {
              // Convert to YYYY-MM-DD format
              date = formatDate(dateObj);
              if (window.fullcalendar.getEventById(date)) {
                window.location.pathname = calendar["nav_link_day"] + "/" + date;
              }
            };
          }

          // Initialize FullCalendar instance
          var calendarEl = document.getElementById(calendar["embed_id"]);
          window.fullcalendar = new FullCalendar.Calendar(
            calendarEl,
            calendarOptions
          );

          window.fullcalendar.render();

          // Build custom header with year dropdown
          $("#" + calendar["embed_id"])
            .prepend(buildHeader(calendar["years"], getUrlYear()))
            .change(function () {
              var selectedYear = $("select").val();
              redirectYear(selectedYear);
            });

          calendar["init"] = true;
        }
      });

      function getPresets() {
        var presetOptions = {
          initialView: "multiMonthYear",
          contentHeight: "auto",
          eventDisplay: "background",
          headerToolbar: false,
          dayHeaderFormat: {
            weekday: "narrow", // Single character weekday. E.g. W
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
        date = dateObj.toLocaleDateString("fr-CA", {
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
        });
        return date;
      }

      function buildHeader(
        years,
        selectedYear,
        headlineTemplate = "All issues for {year}"
      ) {
        // If no selectedYear given, set it to the most recent year
        if (!selectedYear) {
          selectedYear = years[years.length - 1];
        }

        window.fullcalendar.gotoDate(selectedYear);
        var headline =
          '<h3 class="fc-solr-headline">' +
          headlineTemplate.replace("{year}", selectedYear) +
          "</h3>";

        var yearOptions = [];
        years.forEach((year) => {
          if (year == selectedYear) {
            yearOptions.push(
              '<option selected value="' + year + '">' + year + "</option>"
            );
          } else {
            yearOptions.push(
              '<option value="' + year + '">' + year + "</option>"
            );
          }
        });
        var yearSelect =
          '<select class="fc-solr-select-year">' +
          yearOptions.join("\n") +
          "</select>";
        return (
          '<div class="fc-solr-header">' + headline + yearSelect + "</div>"
        );
      }

      function navLinkHint(dateText, dateObj) {
        // Convert to YYYY-MM-DD format
        date = dateObj.toLocaleDateString("fr-CA", {
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
        });
        var targetEvent = window.fullcalendar.getEventById(date);
        if (
          !targetEvent ||
          isNaN(targetEvent.extendedProps.count) ||
          targetEvent.extendedProps.count <= 0
        ) {
          return "No results";
        }
        if (targetEvent.extendedProps.count === 1) {
          return "1 result";
        }
        return targetEvent.extendedProps.count + " results";
      }

      function redirectYear(selectedYear) {
        var urlPath = window.location.pathname.split("/");
        urlPath[urlPath.length - 1] = selectedYear;
        window.location.pathname = urlPath.join("/");
      }

      function getUrlYear() {
        var urlPath = window.location.pathname.split("/");
        return urlPath[urlPath.length - 1];
      }
    },
  };
})(jQuery);
