const profilBox = document.querySelector('.profil-box');
const avatarCircle = document.querySelector('.avatar-circle');

if (avatarCircle && profilBox) {
    avatarCircle.addEventListener('click', () => profilBox.classList.toggle('show'));
}
 // --- Submenu Toggle Logic ---
        function toggleSubmenu(element) {
            const submenu = element.nextElementSibling;
            const arrow = element.querySelector('.arrow');
            
            // Toggle the submenu visibility
            if (submenu) {
                submenu.classList.toggle('show');
                arrow.classList.toggle('rotate');
            }
        }

        // --- Chart.js Configuration (Sales Traffic) ---
        document.addEventListener('DOMContentLoaded', function() {
            const salesEl = document.getElementById('salesChart');
            if (!salesEl) return; // nothing to render on pages without the chart
            const ctx = salesEl.getContext('2d');
            
            // Gradient for the chart line
            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(249, 191, 41, 0.4)'); // Primary color with opacity
            gradient.addColorStop(1, 'rgba(249, 191, 41, 0.0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    datasets: [{
                        label: 'Penjualan (Juta Rupiah)',
                        data: [15, 19, 22, 25, 18, 30, 28, 35, 32, 40, 38, 45],
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: '#f9bf29',
                        borderWidth: 3,
                        pointBackgroundColor: '#3b5d50',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.4 // Makes the line curved smoothly
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: { family: "'Poppins', sans-serif", size: 12 }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });

// Admin store location map initialization is handled directly in admin-page/store_locations.php.
// This file retains general dashboard scripts only.
