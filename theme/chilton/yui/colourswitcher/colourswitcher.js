YUI.add('moodle-theme_chilton-colourswitcher', function(Y) {

// Available colours
var COLOURS = ['blue'];

/**
 * Chilton theme colour switcher class.
 * Initialise this class by calling M.theme_chilton.init
 */
var ColourSwitcher = function() {
    ColourSwitcher.superclass.constructor.apply(this, arguments);
};
ColourSwitcher.prototype = {
    /**
     * Constructor for this class
     * @param {object} config
     */
    initializer : function(config) {
        var i, c;
        // Attach events to the links to change colours so we can do it with
        // JavaScript without refreshing the page
        for (i in COLOURS) {
            c = COLOURS[i];
            // Check if this is the current colour
            if (Y.one(document.body).hasClass('chilton-'+c)) {
                this.set('colour', c);
            }
            Y.all(config.div+' .colour-'+c).on('click', this.setColour, this, c);
        }
    },
    /**
     * Sets the colour being used for the chilton theme
     * @param {Y.Event} e The event that fired
     * @param {string} colour The new colour
     */
    setColour : function(e, colour) {
        // Prevent the event from refreshing the page
        e.preventDefault();
        // Switch over the CSS classes on the body
        Y.one(document.body).replaceClass('chilton-'+this.get('colour'), 'chilton-'+colour);
        // Update the current colour
        this.set('colour', colour);
        // Store the users selection (Uses AJAX to save to the database)
        M.util.set_user_preference('theme_chilton_chosen_colour', colour);
    }
};
// Make the colour switcher a fully fledged YUI module
Y.extend(ColourSwitcher, Y.Base, ColourSwitcher.prototype, {
    NAME : 'Chilton theme colour switcher',
    ATTRS : {
        colour : {
            value : 'blue'
        }
    }
});
// Our chilton theme namespace
M.theme_chilton = M.theme_chilton || {};
// Initialisation function for the colour switcher
M.theme_chilton.initColourSwitcher = function(cfg) {
    return new ColourSwitcher(cfg);
}

}, '@VERSION@', {requires:['base','node']});