/**
 * ShopGraph AI Attributes: repeatable Product Q&A rows (add / remove).
 *
 * Uses event delegation on the panel so rows added after page load are handled
 * too. No dependencies.
 */
( function () {
	'use strict';

	function init() {
		var panel = document.getElementById( 'shopgraph_product_data' );
		if ( ! panel ) {
			return;
		}

		var rows = panel.querySelector( '.shopgraph-qa-rows' );
		if ( ! rows ) {
			return;
		}

		panel.addEventListener( 'click', function ( event ) {
			var addButton = event.target.closest( '.shopgraph-add-qa' );
			if ( addButton ) {
				event.preventDefault();
				addRow( rows );
				return;
			}

			var removeButton = event.target.closest( '.shopgraph-remove-qa' );
			if ( removeButton ) {
				event.preventDefault();
				removeRow( rows, removeButton.closest( '.shopgraph-qa-row' ) );
			}
		} );
	}

	function addRow( rows ) {
		var template = rows.querySelector( '.shopgraph-qa-row' );
		if ( ! template ) {
			return;
		}

		var clone = template.cloneNode( true );
		var inputs = clone.querySelectorAll( 'input' );
		for ( var i = 0; i < inputs.length; i++ ) {
			inputs[ i ].value = '';
		}
		rows.appendChild( clone );

		var firstInput = clone.querySelector( 'input' );
		if ( firstInput ) {
			firstInput.focus();
		}
	}

	function removeRow( rows, row ) {
		if ( ! row ) {
			return;
		}

		var allRows = rows.querySelectorAll( '.shopgraph-qa-row' );
		if ( allRows.length > 1 ) {
			row.parentNode.removeChild( row );
			return;
		}

		// Keep at least one row: clear it instead of removing the last one.
		var inputs = row.querySelectorAll( 'input' );
		for ( var i = 0; i < inputs.length; i++ ) {
			inputs[ i ].value = '';
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
