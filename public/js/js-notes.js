$(function(){

  var
    Notes = function() {
      // make sure db is initialised
      this.load = function(title) {};
      this.save = function(title, note) {};
      this.search =  function(searchstr) {};
    },

    NotesApp = function(notes) {
      // get anchor and/or querystring and load the right page
      // set up listeners for everything
    }
  ;
  
  new NotesApp(new Notes());

}());