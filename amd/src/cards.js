/**
 * Cards module.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

// TODO Handle window resizing/rotating?
// TODO Test Edge

define([
    'jquery',
    'core/ajax',
    'core/notification'
], function($, Ajax, Notification) {

    /**
     * Randomize array element order in-place.
     * Using Durstenfeld shuffle algorithm.
     * @see http://stackoverflow.com/questions/2450954/how-to-randomize-shuffle-a-javascript-array
     */
    function shuffleArray(array) {
        for (var i = array.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = array[i];
            array[i] = array[j];
            array[j] = temp;
        }
        return array;
    }

    var Cards = function(selector, terms) {
        this._container = $(selector);
        this._terms = terms;
        this._selected = null;
    };
    Cards.prototype._container = null;
    Cards.prototype._dryRun = false;
    Cards.prototype._selected = null;
    Cards.prototype._terms = null;

    Cards.prototype.init = function() {
        var pool = [],
            width = this._container.width(),
            height = $(window).height(),
            perRow = 3,
            cardCount = this._terms.length * 2;

        if (cardCount % 2 < cardCount % 3) {
            perRow = 2;
        }
        var cardWidth = Math.floor(width / perRow);
        var cardHeight = Math.min(Math.round((height - 50) / Math.ceil(cardCount / perRow)), 60);

        this._terms.forEach(function(item) {
            pool.push(this._makeCard(item.id, item.term));
            pool.push(this._makeCard(item.id, item.definition));
        }.bind(this));

        var row = 0,
            col = 0;
        shuffleArray(pool);
        pool.forEach(function(item) {
            item.css({
                top: row * cardHeight,
                left: col * cardWidth,
                width: col == perRow  - 1 ? cardWidth : cardWidth - 4,
                height: cardHeight - 4
            })
            this._container.append(item);

            col++;
            if (col >= perRow) {
                col = 0
                row++;
            }
        }.bind(this));

        this._container.css({height: row * cardHeight});

        this._container.on('click', '.flashcard', this._handlePick.bind(this));
    };

    Cards.prototype._checkComplete = function() {
        if (this._container.find('.flashcard.found').length == this._terms.length * 2) {
            this._trigger('complete');
        }
    }

    Cards.prototype._handlePick = function(e) {
        e.preventDefault();
        var card = $(e.currentTarget);

        // It's already invisible.
        if (card.hasClass('found')) {
            return;
        }

        // It's the first out of the two picks.
        if (!this._selected) {
            this._selected = card;
            card.addClass('selected');
            return;
        }

        // We've clicked the selected card.
        if (this._selected.is(card)) {
            return;
        }

        // It's a match!
        if (card.data('id') == this._selected.data('id')) {
            this._selected
                .addClass('found')
                .animate({'opacity': 0});
            card.addClass('found')
                .animate({'opacity': 0});

            this._reportSuccess(this._selected.data('id'));
            this._checkComplete();

        // It's not a match...
        } else {
            var original = this._selected;
            original.addClass('mismatch');
            card.addClass('mismatch');

            this._reportFailure(this._selected.data('id'), card.data('id'));

            setTimeout(function() {
                original.removeClass('mismatch');
                card.removeClass('mismatch');
            }, 600);
        }

        // Reset the selection.
        this._selected.removeClass('selected');
        this._selected = null;
    }

    Cards.prototype._makeCard = function(id, text) {
        var container = $('<div class="flashcard">')
            .data('id', id);

        container.append($('<div>').text(text));
        return container
    };

    Cards.prototype.on = function(action, cb) {
        this._container.on(action, cb);
    }

    Cards.prototype._reportFailure = function(term1id, term2id) {
        if (this._dryRun) {
            return;
        }

        Ajax.call([{
            methodname: 'mod_flashcards_report_failed_association',
            args: {
                term1id: term1id,
                term2id: term2id
            }
        }]);
    }

    Cards.prototype._reportSuccess = function(termid) {
        if (this._dryRun) {
            return;
        }

        Ajax.call([{
            methodname: 'mod_flashcards_report_successful_association',
            args: {
                termid: termid
            }
        }])
    }

    Cards.prototype.setDryRun = function(value) {
        this._dryRun = value;
    }

    Cards.prototype._trigger = function(action) {
        this._container.trigger(action);
    }

    return Cards;

});