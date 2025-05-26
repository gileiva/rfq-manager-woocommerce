jQuery(document).ready(function($) {
    // Agregar el modal al body si no existe
    if ($('#rfq-confirm-modal').length === 0) {
        $('body').append(`
            <div id="rfq-confirm-modal" class="rfq-modal" style="display: none;">
                <div class="rfq-modal-content">
                    <h3>${rfqManagerL10n.cancelConfirmTitle}</h3>
                    <p>${rfqManagerL10n.cancelConfirm}</p>
                    <div class="rfq-modal-buttons">
                        <button class="rfq-modal-cancel">${rfqManagerL10n.cancelNo}</button>
                        <button class="rfq-modal-confirm">${rfqManagerL10n.cancelYes}</button>
                    </div>
                </div>
            </div>
        `);
    }

    // Función para mostrar notificación toast
    function showToast(message, isError = false) {
        const toast = $("<div class=\"rfq-toast-notification" + (isError ? " error" : "") + "\">" + message + "</div>");
        $("body").append(toast);
        
        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    // Manejar el cierre del modal
    $(document).on('click', '.rfq-modal-cancel', function() {
        $('#rfq-confirm-modal').fadeOut();
    });

    // Manejar cancelación de solicitud
    $(document).on('click', '.rfq-cancel-btn', function(e) {
        e.preventDefault();
        var solicitudId = $(this).data("solicitud");
        console.log("[RFQ] Click en .rfq-cancel-btn", this, this.id, solicitudId);
        
        if (!solicitudId) {
            console.error("[RFQ] No se encontró ID de solicitud");
            return;
        }
        
        console.log("[RFQ] ID de solicitud para cancelar:", solicitudId);
        
        // Mostrar modal de confirmación
        $("#rfq-confirm-modal").fadeIn();
        
        console.log("[RFQ] Confirmación de cancelación");
        
        // Manejar confirmación
        $("#rfq-confirm-modal .rfq-modal-confirm").off("click.cancel").on("click.cancel", function() {
            var $button = $(this);
            $button.prop("disabled", true);
            
            console.log("[RFQ] Enviando AJAX para cancelar solicitud:", solicitudId);
            
            $.ajax({
                url: rfqManagerL10n.ajaxurl,
                type: "POST",
                data: {
                    action: "update_solicitud_status",
                    rfq_nonce: rfqManagerL10n.nonce,
                    solicitud_id: solicitudId,
                    solicitud_status: "rfq-historic"
                },
                success: function(response) {
                    if (response.success) {
                        showToast(rfqManagerL10n.cancelSuccess);
                        
                        // Si estamos en la vista de lista
                        if ($("tr[data-solicitud-id]").length) {
                            var $row = $("tr[data-solicitud-id=\"" + solicitudId + "\"]");
                            var $statusCell = $row.find(".rfq-status-cell");
                            
                            // Actualizar la clase y el texto del estado
                            $statusCell.removeClass().addClass("rfq-status-cell status-historic");
                            $statusCell.text("Histórica");
                            
                            // Ocultar el botón de cancelar
                            $("#rfq-cancel-btn-" + solicitudId).hide();
                        } else {
                            // Si estamos en la vista individual
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                    } else {
                        showToast(response.data ? response.data.message : rfqManagerL10n.cancelError, true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("[RFQ] Error AJAX:", {xhr, status, error});
                    showToast(rfqManagerL10n.cancelError, true);
                },
                complete: function() {
                    $("#rfq-confirm-modal").fadeOut();
                    $button.prop("disabled", false);
                }
            });
        });
    });
});
