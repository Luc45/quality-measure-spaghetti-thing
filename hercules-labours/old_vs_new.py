import os
from argparse import Namespace
from datetime import datetime, timedelta
from typing import Dict, List
import numpy as np
from scipy.signal import convolve, slepian

from labours.objects import DevDay
from labours.plotting import deploy_plot, get_plot_path, import_pyplot

def debug_log(message):
    print(f"DEBUG: {message}")

def generate_google_sparkline(data, max_value, color="#000000"):
    """
    Generates a Google Spreadsheet SPARKLINE formula for the given data.
    """
    # Normalize data values based on the maximum value
    normalized_data = [value / max_value if max_value else 0 for value in data]
    # Convert list to string and replace square brackets with curly braces
    data_str = str(normalized_data).replace('[', '{').replace(']', '}')
    return f"=SPARKLINE({data_str},{{\"charttype\",\"column\"; \"max\",1; \"color\",\"{color}\"}})"

def generate_bars(values, max_value, bar_chars):
    bars = []
    for value in values:
        index = int((value / max_value) * (len(bar_chars) - 1))
        bars.append(bar_chars[index])
    return bars

def generate_sparkline(args, name, start_date, end_date, people, days):
    # Define bar characters for the sparkline
    bar_chars = ["▁", "▂", "▃", "▄", "▅", "▆", "▇", "█"]

    google_mode = os.getenv('HERCULES_SPARKLINE_GOOGLE_MODE', 'false').lower() == 'true'

    # Debug logs for input parameters
    debug_log(f"Name: {name}")
    debug_log(f"Start Date: {datetime.fromtimestamp(start_date)}")
    debug_log(f"End Date: {datetime.fromtimestamp(end_date)}")
    debug_log(f"People: {people}")

    # Convert start and end dates to datetime
    start_date_dt = datetime.fromtimestamp(start_date)
    end_date_dt = datetime.fromtimestamp(end_date)

    # Calculate the number of months for the analysis
    num_months = (end_date_dt.year - start_date_dt.year) * 12 + end_date_dt.month - start_date_dt.month + 1
    new_lines = np.zeros(num_months)
    old_lines = np.zeros_like(new_lines)

    totalAdded = 0
    totalChanged = 0

    # Process each day and aggregate by month
    for day, devs in days.items():
        actual_day = start_date_dt + timedelta(days=day)
        if start_date_dt <= actual_day <= end_date_dt:
            month_index = (actual_day.year - start_date_dt.year) * 12 + actual_day.month - start_date_dt.month
            for stats in devs.values():
                new_lines[month_index] += stats.Added
                old_lines[month_index] += stats.Removed + stats.Changed
                debug_log(f"Month Index: {month_index}, Day: {actual_day}, Added: {stats.Added}, Removed: {stats.Removed}, Changed: {stats.Changed}")

    # Debug logs for processed data
    debug_log(f"Processed new_lines data: {new_lines}")
    debug_log(f"Processed old_lines data: {old_lines}")


    # Calculate the 95th percentiles for new and old lines
    percentile_95_new_lines = np.percentile(new_lines, 95)
    percentile_95_old_lines = np.percentile(old_lines, 95)

    # Cap values at the 95th percentile
    new_lines_capped = np.clip(new_lines, a_min=None, a_max=percentile_95_new_lines)
    old_lines_capped = np.clip(old_lines, a_min=None, a_max=percentile_95_old_lines)

    # Generate text-based bar representation for capped new_lines and old_lines
    max_value_new_lines_capped = max(new_lines_capped) if new_lines_capped.size > 0 else 1
    max_value_old_lines_capped = max(old_lines_capped) if old_lines_capped.size > 0 else 1

    new_line_bars = generate_bars(new_lines_capped, max_value_new_lines_capped, bar_chars)
    old_line_bars = generate_bars(old_lines_capped, max_value_old_lines_capped, bar_chars)

    # Combine the bars into a single string
    new_lines_str = ''.join(new_line_bars)
    old_lines_str = ''.join(old_line_bars)

    google_sparkline_new = generate_google_sparkline(new_lines_capped, percentile_95_new_lines, 'green')
    google_sparkline_old = generate_google_sparkline(old_lines_capped, percentile_95_old_lines, 'orange')

    # Print or return the string representation
    #print("New Lines Bar Graph||||", new_lines_str)
    #print("Old Lines Bar Graph||||", old_lines_str)

    #print(f"New PHP Lines (95th percentile)||||", google_sparkline_new)
    #print(f"Changed PHP Lines (95th percentile)||||", google_sparkline_old)

    # Array of cap values
    cap_values = [2500]

    # Iterate over each cap value
    for cap_value in cap_values:
        # Cap the data at the current cap_value
        new_lines_capped = np.clip(new_lines, a_min=None, a_max=cap_value)
        old_lines_capped = np.clip(old_lines, a_min=None, a_max=cap_value)

        # Generate text-based bar representation for capped new_lines and old_lines
        max_value_new_lines_capped = max(new_lines_capped) if new_lines_capped.size > 0 else 1
        max_value_old_lines_capped = max(old_lines_capped) if old_lines_capped.size > 0 else 1

        new_line_bars_capped = generate_bars(new_lines_capped, max_value_new_lines_capped, bar_chars)
        old_line_bars_capped = generate_bars(old_lines_capped, max_value_old_lines_capped, bar_chars)

        # Generate Google Spreadsheet SPARKLINE formulas
        google_sparkline_new = generate_google_sparkline(new_lines_capped, max_value_new_lines_capped, 'green')
        google_sparkline_old = generate_google_sparkline(old_lines_capped, max_value_old_lines_capped, 'orange')

        # Print or store the string representation for the capped data
        #print(f"New Lines Bar Graph ({cap_value} cap)||||", ''.join(new_line_bars_capped))
        #print(f"Old Lines Bar Graph ({cap_value} cap)||||", ''.join(old_line_bars_capped))

        print(f"New PHP Lines per Month (Capped at {cap_value})||||", google_sparkline_new)
        print(f"Changed PHP Lines per Month (Capped at {cap_value})||||", google_sparkline_old)

def show_old_vs_new(
    args: Namespace,
    name: str,
    start_date: int,
    end_date: int,
    people: List[str],
    days: Dict[int, Dict[int, DevDay]],
) -> None:
    if os.getenv('HERCULES_SPARKLINE_MODE') == 'true':
        generate_sparkline(args, name, start_date, end_date, people, days)
        return

    start_date = datetime.fromtimestamp(start_date)
    start_date = datetime(start_date.year, start_date.month, start_date.day)
    end_date = datetime.fromtimestamp(end_date)
    end_date = datetime(end_date.year, end_date.month, end_date.day)
    new_lines = np.zeros((end_date - start_date).days + 2)
    old_lines = np.zeros_like(new_lines)

    for day, devs in days.items():
        for stats in devs.values():
            new_lines[day] += stats.Added
            old_lines[day] += stats.Removed + stats.Changed

    resolution = 32
    window = slepian(max(len(new_lines) // resolution, 1), 0.5)
    new_lines = convolve(new_lines, window, "same")
    old_lines = convolve(old_lines, window, "same")

    plot_x = [start_date + timedelta(days=i) for i in range(len(new_lines))]

    matplotlib, pyplot = import_pyplot(args.backend, args.style)
    fig, axs = pyplot.subplots(1, 4, figsize=(20, 2))  # One row, four columns

    # Plotting
    axs[0].fill_between(plot_x, new_lines, color="#8DB843", label="Changed new lines")
    axs[1].fill_between(plot_x, old_lines, color="#E14C35", label="Changed existing lines")
    axs[2].fill_between(plot_x, new_lines, color="#8DB843", label="Changed new lines")
    axs[3].fill_between(plot_x, old_lines, color="#E14C35", label="Changed existing lines")

    # Set fixed Y-axis for the last two plots
    axs[2].set_ylim(0, 5000)
    axs[3].set_ylim(0, 5000)

    # Set labels and titles
    for ax in axs:
        ax.set_ylabel('PHP LOC')
        ax.legend(loc=2, fontsize=args.font_size)

    # Adjust layout
    fig.tight_layout(rect=[0, 0.03, 1, 0.95])  # Adjust for the overall title

    if args.mode == "all" and args.output:
        output = get_plot_path(args.output, "old_vs_new_single_row")
    else:
        output = args.output
    deploy_plot("", output, args.background)