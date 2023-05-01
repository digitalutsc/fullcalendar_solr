/**
 * @file
 * Invokes the FullCalendar library for each calendar listed in drupalSettings.
 */

(function ($) {
  drupalSettings.FullCalendarSolr.forEach(function (calendar, key) {
    if (calendar["processed"] != true) {

      calendar["options"]["navLinks"] = calendar["day_links"];
      var calendarOptions = {
        ...getPresets(),
        ...calendar["options"],
        navLinkDayClick: function (dateObj, jsEvent) {
          // Convert to YYYY-MM-DD format
          date = dateObj.toLocaleDateString("fr-CA", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
          });
          if (window.fullcalendar.getEventById(date)) {
            window.location.pathname = calendar["day_path"] + "/" + date;
          }
        },
        navLinkHint: navLinkHint,
      };

      var headlineTemplate = "All issues for {year}";

      // Initialize FullCalendar instance
      var calendarEl = document.getElementById(calendar["embed_id"]);
      window.fullcalendar = new FullCalendar.Calendar(
        calendarEl,
        calendarOptions
      );
      window.fullcalendar.render();

      // Build custom header with year dropdown
      $("#" + calendar["embed_id"])
        .prepend(buildHeader(headlineTemplate, calendar["years"], getUrlYear()))
        .change(function () {
          var selectedYear = $("select").val();
          redirectYear(selectedYear);
        });

      calendar["processed"] = true;
    }
  });

  function redirectYear(selectedYear) {
    var urlPath = window.location.pathname.split("/");
    urlPath[urlPath.length - 1] = selectedYear;
    window.location.pathname = urlPath.join("/");
  }

  function getUrlYear() {
    var urlPath = window.location.pathname.split("/");
    return urlPath[urlPath.length - 1];
  }

  function getPresets() {
    var presetOptions = {
      initialView: "multiMonthYear",
      multiMonthMinWidth: 200,
      multiMonthMaxColumns: 4,
      contentHeight: "auto",
      eventDisplay: "background",
      eventColor: "green",
      headerToolbar: false,
      navLinks: true,
      dayHeaderFormat: {
        weekday: "narrow",
      },
    };
    return presetOptions;
  }

  function navLinkHint(dateText, dateObj) {
    // Convert to YYYY-MM-DD format
    date = dateObj.toLocaleDateString("fr-CA", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
    var targetEvent = window.fullcalendar.getEventById(date);
    if (!targetEvent || isNaN(targetEvent.extendedProps.count) || targetEvent.extendedProps.count <= 0) {
      return "No results";
    }
    if (targetEvent.extendedProps.count === 1) {
      return "1 result";
    }
    return targetEvent.extendedProps.count + " results";
  }

  function buildHeader(
    headlineTemplate = "All issues for {year}",
    years,
    selectedYear
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
        yearOptions.push('<option value="' + year + '">' + year + "</option>");
      }
    });
    var yearSelect =
      '<select class="fc-solr-select-year">' +
      yearOptions.join("\n") +
      "</select>";
    return '<div class="fc-solr-header">' + headline + yearSelect + "</div>";
  }
})(jQuery);
