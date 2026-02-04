        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- DataTables Buttons -->
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JS -->
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Enhanced DataTables initialization
        $(document).ready(function() {
            $('.data-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'desc']],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries available",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    zeroRecords: "No matching records found",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });

            // DataTable with export buttons
            $('.data-table-export').DataTable({
                responsive: true,
                pageLength: 10,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12 col-md-12"B>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm me-2'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm me-2'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-info btn-sm'
                    }
                ]
            });

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            $('.select2-multiple').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Select invoices...',
                allowClear: true
            });
        });

        // Auto-hide alerts
        setTimeout(function() {
            $('.alert:not(.alert-permanent)').fadeOut('slow');
        }, 7000);

        // Enhanced confirm delete with SweetAlert2
        function confirmDelete(message) {
            return Swal.fire({
                title: 'Are you sure?',
                text: message || "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                return result.isConfirmed;
            });
        }

        // Enhanced confirm action with SweetAlert2
        function confirmAction(title, message, icon = 'question') {
            return Swal.fire({
                title: title,
                text: message,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                return result.isConfirmed;
            });
        }

        // Success notification
        function showSuccess(message) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }

        // Error notification
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: message,
                confirmButtonColor: '#e74c3c'
            });
        }

        // Loading overlay
        function showLoading() {
            Swal.fire({
                title: 'Processing...',
                html: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        function hideLoading() {
            Swal.close();
        }

        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Bulk selection
        $('#selectAll').on('change', function() {
            $('.invoice-checkbox').prop('checked', this.checked);
            updateBulkActions();
        });

        $('.invoice-checkbox').on('change', function() {
            updateBulkActions();
        });

        function updateBulkActions() {
            const checked = $('.invoice-checkbox:checked').length;
            if (checked > 0) {
                $('#bulkActionsBtn').removeClass('d-none').text(`Handover Selected (${checked})`);
            } else {
                $('#bulkActionsBtn').addClass('d-none');
            }
        }

        // Animate elements on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.stat-card, .card');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                    }
                });
            });

            elements.forEach(element => observer.observe(element));
        }

        // Initialize animations on page load
        document.addEventListener('DOMContentLoaded', animateOnScroll);

        // Chart.js default configuration
        if (typeof Chart !== 'undefined') {
            Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
            Chart.defaults.color = '#7f8c8d';
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.boxWidth = 6;
        }

        // Tooltip initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
