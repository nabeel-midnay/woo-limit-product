/*jQuery(document).ready(function(){ 
    new DataTable('#woo-limit-ids', {
        paging: false,
        stripeClasses: [ 'odd-row', 'even-row' ],
        initComplete: function () {
            this.api()
                .columns()
                .every(function () {
                    let column = this;
                    let title = column.footer().textContent;
     
                    // Create input element
                    let input = document.createElement('input');
                    input.placeholder = title;
                    column.footer().replaceChildren(input);
     
                    // Event listener for user input
                    input.addEventListener('keyup', () => {
                        if (column.search() !== this.value) {
                            column.search(input.value).draw();
                        }
                    });
                });
        }
    });
});*/
jQuery(document).ready(function () {
  var dataTable = new DataTable('#woo-limit-ids', {
    paging: false,
    stripeClasses: ['odd-row', 'even-row'],
    initComplete: function () {
    var api = this.api();
    // Apply the filter when an option is selected
    api.columns().every(function () {
            var column = this;
            /**/
            let title = column.footer().textContent;
         
            // Create input element
            let input = document.createElement('input');
            input.placeholder = title;
            column.footer().replaceChildren(input);
         
            // Event listener for user input
            input.addEventListener('keyup', () => {
                if (column.search() !== this.value) {
                    column.search(input.value).draw();
                }
            });
                       
        jQuery('select', this.footer()).on('change', function () {
            column.search(jQuery(this).val()).draw();
        });
    });
      
       // Create drop-down filter for the third column (index 2)
        /*api.column(7).data().unique().sort().each(function (data) {
            jQuery('#woo-limit-ids tfoot .drop').html('<select class="filter"><option value="">Select</option><option value="' + data + '">' + data + '</option></select>');
        });*/
        var select = jQuery('<select class="filter"><option value="">Select</option></select>').appendTo('#woo-limit-ids tfoot .drop');
      
        api.column(7).data().unique().sort().each(function (data) {
          select.append('<option value="' + data + '">' + data + '</option>');
        });
        // Hide the default input box for the eighth column
        api.columns(7).every(function () {
          var column = this;
          jQuery('input', this.footer()).css('display', 'none');
        });
        // Event listener for select change
        /*jQuery('select.filter', '#woo-limit-ids tfoot .drop').on('change', function () {
          var columnIndex = jQuery(this).closest('td').index();
          var column = api.column(columnIndex);
          column.search(jQuery(this).val()).draw();
        });*/
        select.on('change', function () {
          var value = jQuery(this).val();
          api.column(7).search(value).draw();
        });
      
    }
  });
    jQuery('#clearSelectionsBtn').html('<a class="clearSelections">Clear</a>');
    jQuery('.clearSelections').on('click', function () {
        location.reload();
    });
});


jQuery(document).ready(function() {
    jQuery(".dataExport").click(function(e) {
        // If native export handler is intended, do not run plugin export
        if (jQuery(this).data('native')) {
            return; // let the native handler proceed
        }
        var exportType = jQuery(this).data('type');
        // Ensure the last column (Order Details) is ignored by the plugin-based export too
        jQuery('#woo-limit-ids').tableExport({
            type: exportType,
            escape: 'false',
            ignoreColumn: [8]
        });
    });
});
/*Additional - Oct 31*/
jQuery(document).ready(function() {
    // Select the table and tfoot elements
    var table = jQuery('.dataTable');
    var tfoot = table.find('tfoot');
    tfoot.detach().insertBefore(table.find('tbody'));
  });
/** Clear button - DataTables **/
//jQuery(document).ready(function() {
    
    //var table = jQuery('.dataTable');
    
//});

jQuery(document).ready(function(){
            // Initially hide the content
            jQuery(".tgl").hide();
            // Attach click event to the button
            jQuery(".tglhed").on("click", function(){
                // Toggle the visibility of the content
                jQuery(this).parent().find(".tgl").toggle();
            });
        });
/************************************/

/*jQuery(document).ready(function ($) {
    $('.upload_image_button').click(function () {
        var image = wp.media({
            title: 'Upload Image',
            multiple: false
        }).open().on('select', function (e) {
            var uploadedImage = image.state().get('selection').first();
            var imageURL = uploadedImage.toJSON().url;

            var targetInput = $(this).data('target');
            $(targetInput).val(imageURL);
        });
    });
});*/

jQuery(document).ready(function ($) {
   $('.upload_image_button').click(function () {
       var button = $(this);
       var targetInput = $(this).data('target');

       // Create a media frame
       var mediaUploader = wp.media({
           title: 'Upload Image',
           multiple: false
       });

       // Handle the media selection
       mediaUploader.on('select', function () {
           var attachment = mediaUploader.state().get('selection').first();
           var imageURL = attachment.toJSON().url;

           // Set the selected image URL in the corresponding input field
           $(targetInput).val(imageURL).trigger('change');
       });

       // Open the media uploader
       mediaUploader.open();
   });
});




/************************************/
 