<?php
/*
Plugin Name: PostPerDay
Description: Muestra cuántos posts se publican por día en el admin. Grafica de crecimiento. 

Version: 1.2
*/

if (!defined('ABSPATH')) exit;

// Crear menú en admin
add_action('admin_menu', function() {
    add_menu_page(
        'Posts per Day',
        'Posts per Day',
        'manage_options',
        'posts-per-day',
        'ppd_render_admin_page',
        'dashicons-chart-bar',
        20
    );
});


//graficas y tablas
add_action('admin_enqueue_scripts', function($hook) {

    // IMPORTANTE: usa el slug de tu menú
    if ($hook !== 'toplevel_page_posts-per-day') return;

    wp_enqueue_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js',
        [],
        null,
        true
    );
});




// Renderizar página
function ppd_render_admin_page() {
    global $wpdb;

    // Fechas clave
    $today = date('Y-m-d');
    $end_current_month = date('Y-m-t'); // último día del mes actual
    $end_next_month = date('Y-m-t', strtotime('+1 month'));

    // Query: posts desde hoy hasta fin del siguiente mes
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT 
            DATE(post_date) as fecha,
            COUNT(*) as total
        FROM {$wpdb->posts}
        WHERE post_type = 'post'
        AND post_status IN ('publish','future')
        AND post_date >= %s
        AND post_date <= %s
        GROUP BY DATE(post_date)
        ORDER BY fecha ASC
    ", $today, $end_next_month));

    // Convertir resultados en array asociativo
    $data = [];
    foreach ($results as $row) {
        $data[$row->fecha] = $row->total;
    }

    $labels = json_encode(array_keys($data));
    $values = json_encode(array_values($data));

    
    // Generar TODOS los días (aunque sean 0)
    $start = new DateTime($today);
    $end = new DateTime($end_next_month);

    echo '<div class="wrap">';
    echo '<canvas id="ppdChart" height="100" style="margin-bottom:20px;"></canvas>';
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {

        const canvas = document.getElementById('ppdChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: $labels,
                datasets: [{
                    label: 'Posts per day',
                    data: $values,
                    fill: false,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

    });
    </script>"; 
    echo '<h1>Post per day (current + next month)</h1>';

    echo '<table class="widefat fixed striped">';
    echo '<thead>
            <tr>
                <th>Date</th>
                <th>Posts Schedule/published</th>
            </tr>
          </thead>';
    echo '<tbody>';

    while ($start <= $end) {
        $date = $start->format('Y-m-d');
        $count = isset($data[$date]) ? $data[$date] : 0;

        echo '<tr>';
        echo '<td>' . esc_html($date) . '</td>';
        echo '<td>' . esc_html($count) . '</td>';
        echo '</tr>';

        $start->modify('+1 day');
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}