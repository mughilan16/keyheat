<?php
$db = new SQLite3('..\keyheat.db');
if (!$db) {
    die("Connection failed: " . $db->lastErrorMsg());
}

$query = "SELECT * FROM keyheat ORDER BY count DESC";
$result = $db->query($query);
if (!$result) {
    die("Query failed: " . $db->lastErrorMsg());
}

$data = array();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $data[] = $row;
}
$keys = array_column($data, 'key');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["clear"])) {
        $db->exec("DELETE FROM keyheat");
    }
}
$db->close();
?>
<!DOCTYPE html>
<html>

<head>
    <title>KeyHeat Graph Visualization</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .controls {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .clear-btn {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            color: #007bff;

        }

        .clear-btn:hover {
            background-color: #e8f4fd;
        }

        select,
        input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        label {
            font-weight: bold;
            color: #555;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }

        .heatmap-container {
            display: grid;
            gap: 2px;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .heatmap-cell {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            font-size: 12px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #007bff;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .data-info {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            color: #0066cc;
        }

        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>KeyHeat Data Visualization</h1>

        <?php if (count($data) > 0): ?>
            <div class="data-info">
                📊 Loaded <?php echo count($data); ?> records from keyheat.db
            </div>

            <div class="controls">
                <label for="chartType">Chart Type:</label>
                <select id="chartType" onchange="updateChart()">
                    <option value="bar">Bar Chart</option>
                    <option value="line">Line Chart</option>
                    <option value="pie">Pie Chart</option>
                    <option value="doughnut">Doughnut Chart</option>
                    <option value="heatmap">Keyboard Heatmap</option>
                </select>

                <form method="POST">
                    <div class="button-group">
                        <button type="submit" name="clear" class="clear-btn">
                            Clear
                        </button>
                    </div>
                </form>

            </div>

            <div class="chart-container">
                <canvas id="mainChart"></canvas>
            </div>

            <div id="heatmapContainer" class="heatmap-container" style="display: none;"></div>

            <div class="stats" id="statsContainer"></div>

        <?php else: ?>
            <div class="no-data">
                No data found in the keyheat database. Make sure your database contains data and the table structure is correct.
            </div>
        <?php endif; ?>
    </div>

    <script>
        const sampleData = <?php echo json_encode($data); ?>;

        <?php if (count($data) > 0): ?>
            console.log('Loaded data:', sampleData);
            console.log('Available columns:', Object.keys(sampleData[0]));
        <?php endif; ?>

        let chart = null;
        let keyboardLayout = [
            ['`', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '-', '=', 'backspace'],
            ['tab', 'Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P', '[', ']', '\\'],
            ['caps_lock', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', ';', "'", 'enter'],
            ['shift_l', 'Z', 'X', 'C', 'V', 'B', 'N', 'M', ',', '.', '/', 'shift_r'],
            ['ctrl_l', 'Win', 'Alt_l', 'Space', 'Alt_r', 'Win', 'Menu', 'ctrl_r']
        ];

        function generateColors(count) {
            const colors = [];
            for (let i = 0; i < count; i++) {
                const hue = (i * 360 / count) % 360;
                colors.push(`hsl(${hue}, 70%, 60%)`);
            }
            return colors;
        }

        function updateChart() {
            if (sampleData.length === 0) {
                return;
            }

            const chartType = document.getElementById('chartType').value;
            const dataField = "count";
            const maxItems = 300

            if (chartType === 'heatmap') {
                showKeyboardHeatmap();
                return;
            }

            document.getElementById('heatmapContainer').style.display = 'none';
            document.getElementById('mainChart').style.display = 'block';

            const limitedData = sampleData.slice(0, maxItems);

            // Handle different data types for labels
            const labels = limitedData.map(item => {
                if (item.key) return item.key;
                if (item.name) return item.name;
                return item.id || 'Item ' + (limitedData.indexOf(item) + 1);
            });

            const data = limitedData.map(item => {
                const value = item[dataField];
                return typeof value === 'string' && !isNaN(value) ? parseFloat(value) : value;
            });

            const colors = generateColors(labels.length);

            const ctx = document.getElementById('mainChart').getContext('2d');

            if (chart) {
                chart.destroy();
            }

            const config = {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: dataField.replace('_', ' ').toUpperCase(),
                        data: data,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color.replace('60%', '40%')),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: chartType === 'pie' || chartType === 'doughnut'
                        },
                        title: {
                            display: true,
                            text: `KeyHeat Data - ${dataField.replace('_', ' ').toUpperCase()}`
                        }
                    },
                    scales: chartType === 'bar' || chartType === 'line' ? {
                        y: {
                            beginAtZero: true
                        }
                    } : {}
                }
            };

            chart = new Chart(ctx, config);
        }

        function showKeyboardHeatmap() {
            document.getElementById('mainChart').style.display = 'none';
            document.getElementById('heatmapContainer').style.display = 'grid';

            const container = document.getElementById('heatmapContainer');
            container.innerHTML = '';

            // Find the field that represents key count
            const countField = sampleData.length > 0 && sampleData[0].count ? 'count' :
                sampleData.length > 0 && sampleData[0].count ? 'count' :
                Object.keys(sampleData[0]).find(key => typeof sampleData[0][key] === 'number');

            if (!countField) {
                container.innerHTML = '<div style="text-align: center; color: #666;">No numeric data available for heatmap</div>';
                return;
            }

            // Calculate max value for color scaling
            const maxCount = Math.max(...sampleData.map(item => parseFloat(item[countField]) || 0));

            // Create data map
            const dataMap = {};
            sampleData.forEach(item => {
                const key = item.key || item.name || item.id;
                if (key) {
                    dataMap[key.toString().toUpperCase()] = parseFloat(item[countField]) || 0;
                }
            });

            keyboardLayout.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.style.display = 'flex';
                rowDiv.style.gap = '2px';
                rowDiv.style.justifyContent = 'center';

                row.forEach(key => {
                    const cell = document.createElement('div');
                    cell.className = 'heatmap-cell';

                    const count = dataMap[key.toUpperCase()] || 0;
                    const intensity = maxCount > 0 ? count / maxCount : 0;
                    const red = Math.round(255 * intensity);
                    const blue = Math.round(255 * (1 - intensity));

                    cell.style.backgroundColor = `rgb(${red}, 50, ${blue})`;
                    cell.style.width = key === 'Space' ? '200px' :
                        key === 'Backspace' || key === 'Enter' ? '80px' : '40px';
                    cell.textContent = key.length > 5 ? key.substr(0, 3) + '...' : key;
                    cell.title = `${key}: ${count} ${countField}`;

                    rowDiv.appendChild(cell);
                });

                container.appendChild(rowDiv);
            });
        }



        // Initialize the chart when page loads
        window.onload = function() {
            if (sampleData.length > 0) {
                updateChart();
            }
        };
    </script>
</body>

</html>