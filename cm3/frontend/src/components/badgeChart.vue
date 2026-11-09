<template>
    <div class="badge-chart">
        <div v-if="!chartData || !chartData.labels || chartData.labels.length === 0" class="empty-chart">
            <v-alert type="info" dense text class="mb-0">
                No data to display for the selected filters.
            </v-alert>
        </div>
        <div v-else class="charts-row">
            <!-- Bar chart -->
            <div class="chart-col">
                <div class="chart-title">Period Count</div>
                <div class="chart-panel">
                    <canvas ref="barCanvas"></canvas>
                </div>
            </div>
            <!-- Line chart -->
            <div class="chart-col">
                <div class="chart-title">Running Total</div>
                <div class="chart-panel">
                    <canvas ref="lineCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Controls bar -->
        <div class="chart-controls">
            <span class="control-label">Resolution</span>
            <v-slider
                v-model="bucketWidth"
                :min="2"
                :max="16"
                :step="1"
                min-width="80"
                max-width="140"
                dense
                inline
                hide-details
                class="ml-2"
            ></v-slider>
            <span class="control-value">{{ bucketWidth }}px</span>
        </div>
    </div>
</template>

<script>
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend,
    BarController,
    LineController,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend,
    BarController,
    LineController,
);

const COLORS = ['#3f51b5', '#009688', '#ff9800', '#f44336', '#9c27b0',
                '#2196f3', '#4caf50', '#ff5722', '#795548', '#607d8b'];

export default {
    name: 'BadgeChart',
    props: {
        chartData: {
            type: Object,
            default: null,
        },
        loading: {
            type: Boolean,
            default: false,
        },
        column: {
            type: String,
            default: 'total_count',
        },
    },
    data() {
        return {
            barChart: null,
            lineChart: null,
            bucketWidth: 4,
        };
    },
    watch: {
        chartData: {
            immediate: true,
            handler() {
                this.$nextTick(() => {
                    this.renderCharts();
                });
            },
        },
        column() {
            this.$nextTick(() => {
                this.renderCharts();
            });
        },
        bucketWidth() {
            this.$nextTick(() => {
                this.renderCharts();
            });
        },
    },
    beforeDestroy() {
        if (this.barChart) {
            this.barChart.destroy();
            this.barChart = null;
        }
        if (this.lineChart) {
            this.lineChart.destroy();
            this.lineChart = null;
        }
    },
    methods: {
        /**
         * Shorten period labels by extracting common prefix.
         * "2024-01", "2024-02" → "01", "02" (tooltip shows full "2024-01")
         */
        formatLabels(labels) {
            if (labels.length < 2) return labels.map(l => ({ display: this.shortenLabel(l), original: l }));

            let prefix = labels[0];
            for (let i = 1; i < labels.length && prefix.length > 1; i++) {
                while (!labels[i].startsWith(prefix)) {
                    prefix = prefix.slice(0, -1);
                }
            }

            if (prefix.length >= 2) {
                return labels.map(l => ({
                    display: l.slice(prefix.length) || l,
                    original: l,
                }));
            }

            return labels.map(l => ({ display: this.shortenLabel(l), original: l }));
        },

        shortenLabel(label) {
            const m = label.match(/(\d{4})[-/](\d{1,2})[-/]?(\d{1,2})?/);
            if (m) {
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                const month = parseInt(m[2]) - 1;
                return months[month] + (m[3] ? ' ' + m[3] : '');
            }
            return label.length > 6 ? label.slice(-5) : label;
        },

        /**
         * Downsample multiple datasets of the same type independently.
         * All datasets share the same labels and bucket structure.
         */
        downsampleByType(labels, datasets, bucketWidthPx, pixelWidth, sumValues) {
            const maxPoints = Math.floor(pixelWidth / bucketWidthPx);
            if (maxPoints >= labels.length || maxPoints < 1) {
                return { labels, datasets: datasets };
            }

            const step = Math.ceil(labels.length / maxPoints);
            const lastBucket = Math.floor((labels.length - 1) / step);

            // Compute sampled labels ONCE outside the map
            const sampledLabels = [];
            for (let b = 0; b <= lastBucket; b++) {
                const end = Math.min((b + 1) * step, labels.length);
                sampledLabels.push(labels[end - 1]);
            }

            // Sample each dataset against the same bucket boundaries
            const sampledDataSets = datasets.map(ds => {
                const origData = ds.data || [];
                const sampledData = [];
                for (let b = 0; b <= lastBucket; b++) {
                    const start = b * step;
                    const end = Math.min(start + step, labels.length);
                    if (sumValues) {
                        let sum = 0;
                        for (let i = start; i < end; i++) {
                            sum += (origData[i] || 0);
                        }
                        sampledData.push(sum);
                    } else {
                        let lastVal = 0;
                        for (let i = start; i < end; i++) {
                            if (origData[i] != null) lastVal = origData[i];
                        }
                        sampledData.push(lastVal);
                    }
                }
                return { ...ds, data: sampledData };
            });

            return { labels: sampledLabels, datasets: sampledDataSets };
        },

        /**
         * Common legend filter: hide datasets with all-zero data.
         */
        zeroDataFilter(item) {
            if (!item || !item.dataset) return true;
            var data = item.dataset.data || [];
            for (var i = 0; i < data.length; i++) {
                if (data[i]) return true;
            }
            return false;
        },

        renderCharts() {
            if (!this.chartData || !this.chartData.labels || this.chartData.labels.length === 0) {
                return;
            }

            const barCanvas = this.$refs.barCanvas;
            const lineCanvas = this.$refs.lineCanvas;
            if (!barCanvas || !lineCanvas) return;

            if (this.barChart) {
                this.barChart.destroy();
                this.barChart = null;
            }
            if (this.lineChart) {
                this.lineChart.destroy();
                this.lineChart = null;
            }

            const pixelWidth = barCanvas.clientWidth || 800;
            const formattedLabels = this.formatLabels(this.chartData.labels);
            const datasets = this.chartData.datasets || [];

            if (datasets.length === 0) return;

            const barDatasets = datasets.filter(d => d.type === 'bar');
            const lineDatasets = datasets.filter(d => d.type === 'line');

            // Downsample each dataset independently
            const barResult = barDatasets.length > 0
                ? this.downsampleByType(formattedLabels, barDatasets, this.bucketWidth, pixelWidth, true)
                : { labels: [], datasets: [] };
            const lineResult = lineDatasets.length > 0
                ? this.downsampleByType(formattedLabels, lineDatasets, this.bucketWidth, pixelWidth, false)
                : { labels: [], datasets: [] };

            // ---- Bar chart ----
            if (barDatasets.length > 0) {
                // Strip yAxisID to prevent dual-axis behavior
                const barChartDatasets = barResult.datasets.map(d => {
                    const cd = { ...d };
                    delete cd.yAxisID;
                    delete cd.order;
                    return cd;
                });
                this.barChart = new ChartJS(barCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: barResult.labels.map(l => l.display),
                        datasets: barChartDatasets,
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'nearest',
                            intersect: false,
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'nearest',
                                intersect: false,
                                filter: (tooltipItem) => {
                                    return tooltipItem.raw !== null && tooltipItem.raw !== 0;
                                },
                                callbacks: {
                                    title: (items) => {
                                        if (!items.length) return '';
                                        const label = items[0].label;
                                        for (const pl of barResult.labels) {
                                            if (pl.display === label) return pl.original;
                                        }
                                        return label;
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                stacked: true,
                                beginAtZero: true,
                            },
                        },
                    },
                });
            }

            // ---- Line chart ----
            if (lineDatasets.length > 0) {
                // Strip yAxisID to prevent auto-created secondary axis
                const lineChartDatasets = lineResult.datasets.map(d => {
                    const cd = { ...d };
                    delete cd.yAxisID;
                    delete cd.order;
                    return cd;
                });
                this.lineChart = new ChartJS(lineCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: lineResult.labels.map(l => l.display),
                        datasets: lineChartDatasets,
                    },
                    options: {
                        animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'nearest',
                            intersect: false,
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'nearest',
                                intersect: false,
                                filter: (tooltipItem) => {
                                    return tooltipItem.raw !== null && tooltipItem.raw !== 0;
                                },
                                callbacks: {
                                    title: (items) => {
                                        if (!items.length) return '';
                                        const label = items[0].label;
                                        for (const pl of lineResult.labels) {
                                            if (pl.display === label) return pl.original;
                                        }
                                        return label;
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true,
                            },
                        },
                    },
                });
            }
        },

        columnLabel(metric) {
            var labels = {
                paid_count: 'Paid',
                printed_count: 'Printed',
                checked_in_count: 'Checked In',
                total_count: 'Total',
            };
            return labels[metric] || metric;
        },
    },
};
</script>

<style scoped>
.badge-chart {
    padding: 8px 16px 16px;
    position: relative;
}

.empty-chart {
    padding: 8px 0;
}

.charts-row {
    display: flex;
    gap: 16px;
    margin-bottom: 4px;
}

.chart-col {
    display: flex;
    flex-direction: column;
}

.chart-panel {
    flex: 0 0 280px;
    min-width: 0;
    position: relative;
}

.chart-panel canvas {
    width: 100% !important;
    height: 280px !important;
}

.chart-title {
    font-size: 11px;
    color: #666;
    font-weight: 600;
    text-align: center;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.chart-controls {
    display: flex;
    align-items: center;
    padding: 4px 0 8px;
    gap: 6px;
}

.control-label {
    font-size: 11px;
    color: #666;
    white-space: nowrap;
}

.control-value {
    font-size: 11px;
    color: #999;
    min-width: 36px;
    text-align: right;
}

:deep(.v-slider) {
    margin: 0;
    height: 24px !important;
}

:deep(.v-slider__thumb) {
    width: 14px !important;
    height: 14px !important;
}

:deep(.v-slider__track-container) {
    height: 2px !important;
}

:deep(.v-slider__track-fill) {
    height: 2px !important;
}
</style>
