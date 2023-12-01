import os
from argparse import Namespace
from datetime import datetime, timedelta
from typing import Dict, List
import numpy as np
from collections import defaultdict, namedtuple

class DevDay(
    namedtuple("DevDay", ("Commits", "Added", "Removed", "Changed", "Languages"))
):
    def add(self, dd: 'DevDay') -> 'DevDay':
        langs = defaultdict(lambda: [0] * 3)
        for key, val in self.Languages.items():
            for i in range(3):
                langs[key][i] += val[i]
        for key, val in dd.Languages.items():
            for i in range(3):
                langs[key][i] += val[i]
        return DevDay(
            Commits=self.Commits + dd.Commits,
            Added=self.Added + dd.Added,
            Removed=self.Removed + dd.Removed,
            Changed=self.Changed + dd.Changed,
            Languages=dict(langs),
        )

def debug_log(message):
    pass
    #print(f"DEBUG: {message}")

def generate_google_sparkline(data, cap, color="#000000"):
    """
    Generates a Google Spreadsheet SPARKLINE formula for the given data.
    """
    # Cap data values based on the given cap
    capped_data = [min(value, cap) for value in data]
    # Convert list to string and replace square brackets with curly braces
    data_str = str(capped_data).replace('[', '{').replace(']', '}')
    return f"=SPARKLINE({data_str},{{\"charttype\",\"column\"; \"max\",{cap}; \"color\",\"{color}\"}})"

def generate_google_sparkline_uncapped(data, color="#000000"):
    """
    Generates a Google Spreadsheet SPARKLINE formula for the given data, without capping.
    Avoids scientific notation and ensures numbers are not converted to strings.
    """
    # Format each number to avoid scientific notation and create a string representation of the array
    data_str = '{' + ', '.join(f"{value:.0f}" for value in data) + '}'
    return f"=SPARKLINE({data_str},{{\"charttype\",\"column\"; \"color\",\"{color}\"}})"

def show_old_vs_new(
    args: Namespace,
    name: str,
    start_date: int,
    end_date: int,
    people: List[str],
    days: Dict[int, Dict[int, DevDay]],
) -> None:
    # Debug logs for input parameters
    debug_log(f"Name: {name}")
    debug_log(f"Start Date: {datetime.fromtimestamp(start_date)}")
    debug_log(f"End Date: {datetime.fromtimestamp(end_date)}")
    debug_log(f"People: {people}")

    # Convert start and end dates to datetime
    start_date_dt = datetime.fromtimestamp(start_date)
    end_date_dt = datetime.fromtimestamp(end_date)

    # Calculate the number of months for the analysis and initialize arrays
    num_months = (end_date_dt.year - start_date_dt.year) * 12 + end_date_dt.month - start_date_dt.month + 1
    new_lines = np.zeros(num_months)
    old_lines = np.zeros_like(new_lines)

    # Process each day and aggregate by month
    for day, devs in days.items():
        actual_day = start_date_dt + timedelta(days=day)
        if start_date_dt <= actual_day <= end_date_dt:
            month_index = (actual_day.year - start_date_dt.year) * 12 + actual_day.month - start_date_dt.month
            for stats in devs.values():
                new_lines[month_index] += stats.Added
                old_lines[month_index] += stats.Removed + stats.Changed

    # Debug logs for processed data
    debug_log(f"Processed new_lines data: {new_lines}")
    debug_log(f"Processed old_lines data: {old_lines}")

    # Generate Google Spreadsheet SPARKLINE formulas for uncapped data
    google_sparkline_new_uncapped = generate_google_sparkline_uncapped(new_lines, 'green')
    google_sparkline_old_uncapped = generate_google_sparkline_uncapped(old_lines, 'orange')

    print(f"New Lines per Month||||", google_sparkline_new_uncapped)
    print(f"Changed Lines per Month||||", google_sparkline_old_uncapped)

    # Generate Google Spreadsheet SPARKLINE formulas for percentile data
    google_sparkline_new_percentile = generate_google_sparkline_uncapped(np.minimum(new_lines, np.percentile(new_lines, 99)), 'green')
    google_sparkline_old_percentile = generate_google_sparkline_uncapped(np.minimum(old_lines, np.percentile(old_lines, 99)), 'orange')

    print(f"New Lines per Month (99th percentile)||||", google_sparkline_new_percentile)
    print(f"Changed Lines per Month (99th percentile)||||", google_sparkline_old_percentile)

    # Array of cap values
    cap_values = [2500, 1000, 100]

    # Iterate over each cap value
    for cap_value in cap_values:
        # Cap the data at the current cap_value
        new_lines_capped = np.clip(new_lines, a_min=None, a_max=cap_value)
        old_lines_capped = np.clip(old_lines, a_min=None, a_max=cap_value)

        # Generate Google Spreadsheet SPARKLINE formulas
        google_sparkline_new = generate_google_sparkline(new_lines_capped, cap_value, 'green')
        google_sparkline_old = generate_google_sparkline(old_lines_capped, cap_value, 'orange')

        print(f"New Lines per Month (Capped at {cap_value})||||", google_sparkline_new)
        print(f"Changed Lines per Month (Capped at {cap_value})||||", google_sparkline_old)