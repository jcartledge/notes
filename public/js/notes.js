jQuery(function(){
  $.notesApp = function(base_url) {
    window.highlight_selection = function() {
      var href = $("form#note-form").attr("action");
      var results = $("#search-results-container a");
      results.removeClass("selected").each(function(){
        var this_href = base_url + unescape(this.href).replace(location.href, "")
        if(this_href == href) $(this).addClass("selected");
      });
      if(!results.filter(".search-selected").length) $(results[0]).addClass("search-selected");
    };
    //load note inline
    var load_link_inline = function(e) {
      if(!$("#note-container").length) { $("body").append("<div id=\"note-container\"></div>"); }
      $("#note-container").load(e.href ? e.href : this.href, highlight_selection);
      return false;
    };
    //result selection
    var select_result = function() {
      $("#search-results a").removeClass("search-selected");
      $(this).addClass("search-selected");
    }
    $("#search-results a").live("click", load_link_inline);
    $("#search-results a").live("mouseover", select_result);
    //search results as you type
    $("input:submit").hide();
    var search_text;
    setInterval(function() {
      if(search_text != $("#search").attr("value")) {
        search_text = $("#search").attr("value");
        $("#search-results-container").load(base_url + "?search=" + escape(search_text), function() {
          $("#search-results a").focus(select_result);
          highlight_selection();
        });
        document.title = search_text;
      }
    }, 500);
    // enter in search field
    $("#search").keydown(function(e){
      var el = $("#search-results-container a.search-selected")[0];
      switch(e.keyCode) {
        case 13:                  // ENTER
          load_link_inline(el);
          highlight_selection();
          return false;
        case 27:
          return !$("#note textarea").focus();
      }
    }).focus(function() {
      $("#search-results-container").slideDown("fast");
      $(this).removeClass("recede").select();
    });
    // listen for arrow up and down in search container
    $("#search-container").keyup(function(e) {
      switch(e.keyCode) {
        case 38:      // UP
        case 40:      // DOWN
          var
            dir = e.keyCode - 39,
            as = $("#search-results a"),
            new_selected;
          as.each(function(){
            if($(this).hasClass("search-selected")) {
              $(this).removeClass("search-selected");
              new_selected = Math.max(0, Math.min(as.length - 1, as.index($(this)) + dir));
            }
          });
          as.eq(new_selected).addClass("search-selected");
          return false;
      }
    });
    $("#search").focus();
  };
  $.noteLoaded = function(title, base_url) {
    var previous_text, dirty, ta = $("textarea"), loaded_text = ta.attr("value"), shift_down;
    $("input:submit").hide();
    ta.elastic();
    // watch textarea
    ta.keydown(check_key);
    ta.keyup(check_dirty);
    clearInterval(window.save_interval);
    window.save_interval = setInterval(save, 800);
    function tab(shift_down) {
      var range = ta.caret(),
          caret = isNaN(range.start) ? 0 : range.start,
          val = ta.val();
      if(shift_down) {   // untab
        //does the line have leading whitespace?
        //need to know the start and end of the line we\'re in
        var search_text = val.substr(0, caret).split("").reverse().join(""),
            chars_from_start = search_text.search(/[\r\n]/),
            line = val.substr(caret - chars_from_start).split(/[\r\n]/, 1).shift(),
            ws = line.match(/^[ \t]*/)[0].length;
        if(ws) {
          var ws_diff = ws % 2 ? 1 : 2;
          ta.val(val.substr(0, caret - chars_from_start) + val.substr(caret - (chars_from_start - ws_diff)));
          ta.caret(caret - ws_diff);
        }
      } else {          // tab
        // move caret to end of any whitespace we might be in
        caret += val.substr(caret).match(/^[ \t]*/)[0].length;
        var search_text = val.substr(0, caret).split("").reverse().join(""),
          chars_from_start = Math.max(0, search_text.search(/[\r\n]/));
        if(chars_from_start < 0) chars_from_start = search_text.length;
        var chars_to_insert = chars_from_start % 2 ? " " : "  ";
        ta.val(val.substr(0, caret) + chars_to_insert + val.substr(caret, val.length));
        ta.caret(caret + chars_to_insert.length);
      }
    }
    function indent_newline() {
      // find start of line
      // get leading whitespace
      // add leading cr
      var range = ta.caret(),
          caret = isNaN(range.start) ? 0 : range.start,
          val = ta.val(),
          search_text = val.substr(0, caret).split("").reverse().join(""),
          chars_from_start = Math.max(0, search_text.search(/[\r\n]/)),
          line = val.substr(caret - chars_from_start).split(/[\r\n]/, 1).shift(),
          ws = line.match(/^[ \t]*/)[0];
      console.log(chars_from_start);
      // insert
      ta.val(val.substr(0, caret) + "\r" + ws + val.substr(caret, val.length));
      // move caret
      ta.caret(caret + ws.length + 1);
    }
    function check_key(e) {
      if(e) switch(e.keyCode) {
        case 27:    //ESC
          $("#search").focus();
          break;
        case 16:    //SHIFT
          shift_down = true;
          break;
        case 9:     //TAB
          tab(shift_down);
          return false;
        case 13:     //ENTER
          indent_newline();
          return false;
      }
    }
    function check_dirty(e) {
      if(e && e.keyCode == 16) shift_down = false;
      var dirty = (loaded_text != ta.attr("value"));
      //$("textarea").css("background-color", (dirty ? "#fdd" : "white"));
      $("title").html(dirty ? title + " [modified]" : title);
      return dirty;
    }
    function save() {
      document.title = title;
      if((previous_text == ta.attr("value")) && check_dirty()) {
        var form = $("#note-form");
        jQuery.post(form.attr("action"), form.serialize(), function(data) {
          loaded_text = data;
          if(!check_dirty()) {
            var search_text = $("#search").attr("value");
            $("#search-results-container").load(base_url + "?search=" + escape(search_text), highlight_selection);
          }
        });
      }
      previous_text = ta.attr("value");
    }
    document.title = title;
    // hide results
    ta.focus(function(){
      $("#search-results-container").slideUp("fast");
      $("#search").addClass("recede");
    });
    ta.focus();
  }
});