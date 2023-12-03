import pandas as pd
import plotly.express as px

def process_and_generate_heatmap(file_path, identifier=''):
    # Adjust file names based on the identifier
    base_filename = f"{file_path.split('/')[-1].split('.')[0]}{identifier}"
    txt_filename = f"correlation-heatmap-{base_filename}.txt"
    html_filename = f"correlation_heatmap-{base_filename}.html"
    png_filename = f"correlation_heatmap-{base_filename}.png"

    ### Text Correlation

    try:
        # Read the CSV file
        data = pd.read_csv(file_path)

        # Check if 'Aggregated Rating' is in the columns
        if 'Aggregated Rating' in data.columns:
            # Calculate the correlation of 'Aggregated Rating' with other features
            correlation_with_aggregated_rating = data.corrwith(data['Aggregated Rating']).drop('Aggregated Rating')

            # Convert the correlation Series to a string for easy display
            correlation_text = correlation_with_aggregated_rating.to_string()
        else:
            correlation_text = "Column 'Aggregated Rating' not found in the data."

    except Exception as e:
        correlation_text = f"An error occurred while processing the file: {e}"

    # Write the correlation data to a text file
    with open(txt_filename, "w") as file:
        file.write(correlation_text)

    print("Correlation data written to correlation-heatmap.txt")

    ### Heatmap Correlation

    data = pd.read_csv(file_path)

    data = data.dropna()

    # Remove constant columns
    data = data.loc[:, data.std() > 0]

    # Calculate the correlation matrix
    correlation_matrix = data.corr()

    # Custom 'coolwarm'-like colorscale
    custom_colorscale = [
        [0.0, 'blue'],  # blue at 0%
        [0.5, 'white'], # white at 50%
        [1.0, 'red']    # red at 100%
    ]

    # Generate a heatmap using Plotly
    fig = px.imshow(correlation_matrix,
                    labels=dict(x="Variable 1", y="Variable 2", color="Correlation"),
                    x=correlation_matrix.columns,
                    y=correlation_matrix.columns,
                    text_auto=True,  # Automatically add text in each cell
                    color_continuous_scale=custom_colorscale  # Set custom colorscale
                   )

    fig.update_layout(
        title="Interactive Correlation Heatmap",
        autosize=False,
        width=3000,  # Adjusted width
        height=3000,  # Adjusted height
        xaxis_showgrid=False,
        yaxis_showgrid=False,
        xaxis=dict(tickangle=-45, side="bottom", fixedrange=True),
        yaxis=dict(tickangle=0, fixedrange=True),
        margin=dict(l=10, r=10, b=10, t=50)  # Adjust top margin to accommodate annotations
    )

    # Add annotations for the top axis labels
    for i, col in enumerate(correlation_matrix.columns):
        fig.add_annotation(
            dict(
                font=dict(color="black", size=12),
                x=i,
                y=1.030,  # Positioning the annotation slightly above the top
                showarrow=False,
                text=col,
                align="left",  # Aligning text to the left
                width=200,  # Fixed width for the annotation box
                textangle=-90,
                xref="x",
                yref="paper"
            )
        )

    # Save the plot as an HTML file
    fig.write_html(html_filename)

    # Save the plot as a PNG file
    fig.write_image(png_filename)

    # JavaScript to be appended
    javascript_script = """
<script>
    var myPlot = document.querySelector('.plotly-graph-div');

    myPlot.on('plotly_click', function(data) {
        console.log("Click event triggered");

        var clickedIndex = data.points[0].pointIndex; // Get the clicked row and column indices
        var clickedRow = clickedIndex[0];
        var clickedColumn = clickedIndex[1];

        console.log("Clicked Row: ", clickedRow, "Clicked Column: ", clickedColumn);

        if (data.event.button === 0) { // Left mouse button
            // Horizontal line logic
            addHorizontalLine(clickedRow);
        } else if (data.event.button === 1) { // Middle mouse button
            // Vertical line logic
            addVerticalLine(clickedColumn);
        }
    });

    function addHorizontalLine(rowIndex) {
        console.log("Adding horizontal line at row: ", rowIndex);
        // Calculate y0 and y1 for the horizontal line
        var y0 = rowIndex - 0.5;
        var y1 = rowIndex + 0.5;

        var numberOfColumns = myPlot.data[0].x.length;
        var x0 = -0.5;
        var x1 = numberOfColumns - 0.5;

        var horizontalLine = {
            type: 'rect',
            x0: x0,
            y0: y0,
            x1: x1,
            y1: y1,
            line: {
                color: 'black',
                width: 2
            }
        };

        Plotly.relayout(myPlot, { shapes: [horizontalLine] });
    }

    function addVerticalLine(columnIndex) {
        console.log("Adding vertical line at column: ", columnIndex);
        // Calculate x0 and x1 for the vertical line
        var x0 = columnIndex - 0.5;
        var x1 = columnIndex + 0.5;

        var numberOfRows = myPlot.data[0].y.length;
        var y0 = -0.5;
        var y1 = numberOfRows - 0.5;

        var verticalLine = {
            type: 'rect',
            x0: x0,
            y0: y0,
            x1: x1,
            y1: y1,
            line: {
                color: 'black',
                width: 2
            }
        };

        Plotly.relayout(myPlot, { shapes: [verticalLine] });
    }

    // Function to clear all lines when ESC key is pressed
    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape") {
            console.log("ESC pressed, clearing all lines");
            Plotly.relayout(myPlot, { shapes: [] });
        }
    });
</script>

    """

    # Open the HTML file in append mode and write the JavaScript
    with open(html_filename, "a") as file:
        file.write(javascript_script)

process_and_generate_heatmap('../machine.csv')
process_and_generate_heatmap('../machine-small.csv')