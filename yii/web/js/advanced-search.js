document.addEventListener("DOMContentLoaded", function() {
  var modal = document.getElementById("advancedSearchModal");
  var input = document.getElementById("advanced-search-input");
  var searchBtn = document.getElementById("advanced-search-btn");
  var resultsContainer = document.getElementById("advanced-search-results");

  if (!modal || !input || !searchBtn || !resultsContainer) return;

  var abortController = null;
  var activeIndex = -1;

  var groupLabels = {
    contexts: "Contexts",
    fields: "Fields",
    templates: "Templates",
    instances: "Generated Prompts",
    scratchPads: "Scratch Pads"
  };

  function escapeHtml(text) {
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function highlightMatch(text, query) {
    if (!query) return escapeHtml(text);
    var escaped = escapeHtml(text);
    var regex = new RegExp("(" + query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + ")", "gi");
    return escaped.replace(regex, "<mark>$1</mark>");
  }

  function getSelectedTypes() {
    var selected = [];
    var checkboxes = document.querySelectorAll(".advanced-search-type:checked");
    checkboxes.forEach(function(cb) {
      selected.push(cb.value);
    });
    return selected;
  }

  function getSelectedMode() {
    var modeRadios = document.querySelectorAll(".advanced-search-mode");
    for (var i = 0; i < modeRadios.length; i++) {
      if (modeRadios[i].checked) {
        return modeRadios[i].value;
      }
    }
    return "phrase";
  }

  function renderResults(data, query) {
    var html = "";
    var hasResults = false;

    Object.keys(groupLabels).forEach(function(key) {
      var label = groupLabels[key];
      var items = data[key];
      if (!items || items.length === 0) return;

      hasResults = true;
      html += '<div class="advanced-search-group">';
      html += '<div class="advanced-search-group-title">' + escapeHtml(label) + "</div>";

      items.forEach(function(item) {
        html += '<a href="' + escapeHtml(item.url) + '" class="advanced-search-item" data-url="' + escapeHtml(item.url) + '">';
        html += '<div class="advanced-search-item-name">' + highlightMatch(item.name, query) + "</div>";
        html += '<div class="advanced-search-item-subtitle">' + escapeHtml(item.subtitle) + "</div>";
        html += "</a>";
      });

      html += "</div>";
    });

    if (!hasResults) {
      html = '<div class="advanced-search-empty">No results found</div>';
    }

    resultsContainer.innerHTML = html;
    activeIndex = -1;
    updateActiveItem();
  }

  function getItems() {
    return resultsContainer.querySelectorAll(".advanced-search-item");
  }

  function updateActiveItem() {
    var items = getItems();
    for (var i = 0; i < items.length; i++) {
      if (i === activeIndex) {
        items[i].classList.add("active");
      } else {
        items[i].classList.remove("active");
      }
    }

    if (activeIndex >= 0 && items[activeIndex]) {
      items[activeIndex].scrollIntoView({ block: "nearest" });
    }
  }

  function navigateToActive() {
    var items = getItems();
    if (activeIndex >= 0 && items[activeIndex]) {
      window.location.href = items[activeIndex].dataset.url;
    }
  }

  function performSearch() {
    var query = input.value.trim();

    if (abortController) {
      abortController.abort();
    }

    if (query.length < 2) {
      resultsContainer.innerHTML = '';
      return;
    }

    abortController = new AbortController();
    resultsContainer.innerHTML = '<div class="advanced-search-loading">Searching...</div>';

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var headers = {
      "X-Requested-With": "XMLHttpRequest",
      "Accept": "application/json"
    };
    if (csrfToken) {
      headers["X-CSRF-Token"] = csrfToken.getAttribute("content");
    }

    var url = "/search/advanced?q=" + encodeURIComponent(query);

    var types = getSelectedTypes();
    types.forEach(function(type) {
      url += "&types[]=" + encodeURIComponent(type);
    });

    url += "&mode=" + encodeURIComponent(getSelectedMode());

    fetch(url, {
      method: "GET",
      headers: headers,
      signal: abortController.signal
    })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.success) {
          renderResults(result.data, query);
        } else {
          resultsContainer.innerHTML = '<div class="advanced-search-empty">Search failed</div>';
        }
      })
      .catch(function(error) {
        if (error.name !== "AbortError") {
          resultsContainer.innerHTML = '<div class="advanced-search-empty">Search failed</div>';
        }
      });
  }

  searchBtn.addEventListener("click", performSearch);

  input.addEventListener("keydown", function(e) {
    var items = getItems();

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        if (items.length > 0) {
          activeIndex = Math.min(activeIndex + 1, items.length - 1);
          updateActiveItem();
        }
        break;

      case "ArrowUp":
        e.preventDefault();
        if (items.length > 0) {
          activeIndex = Math.max(activeIndex - 1, 0);
          updateActiveItem();
        }
        break;

      case "Enter":
        e.preventDefault();
        if (activeIndex >= 0 && items[activeIndex]) {
          navigateToActive();
        } else {
          performSearch();
        }
        break;
    }
  });

  // Focus input when modal opens
  modal.addEventListener("shown.bs.modal", function() {
    input.focus();
  });

  // Clear results when modal closes
  modal.addEventListener("hidden.bs.modal", function() {
    input.value = "";
    resultsContainer.innerHTML = '';
    activeIndex = -1;
  });
});
