import pandas as pd
import plotly.express as px

# Load your CSV file
file_path = '../machine.csv'  # Replace with your CSV file path
data = pd.read_csv(file_path)

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
            y=1.020,  # Positioning the annotation slightly above the top
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
fig.write_html("correlation_heatmap.html")

# Save the plot as a PNG file
fig.write_image("correlation_heatmap.png")

# JavaScript to be appended
javascript_script = """
<script>
    var myPlot = document.querySelector('.plotly-graph-div');

    myPlot.on('plotly_click', function(data) {
        console.log("Click event triggered");

        var clickedRow = data.points[0].pointIndex[0]; // Assuming first element is the row index
        console.log("Clicked Row: ", clickedRow);

        // Calculate the coordinates for the border
        var y0 = clickedRow - 0.5; // Start just before the clicked row
        var y1 = clickedRow + 0.5; // End just after the clicked row
        console.log("Border Y Coordinates: ", y0, y1);

        var numberOfColumns = myPlot.data[0].x.length;
        var x0 = -0.5; // Assuming your x-axis starts at 0
        var x1 = numberOfColumns - 0.5;
        console.log("Border X Coordinates: ", x0, x1);

        // Define the border as a rectangular shape
        var borderShape = {
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
        console.log("Border Shape: ", borderShape);

        // Update the layout to include the new shape
        var layoutUpdate = {
            shapes: [borderShape]
        };

        Plotly.relayout(myPlot, layoutUpdate);
        console.log("Layout updated with new shape");
    });
</script>
"""

# Open the HTML file in append mode and write the JavaScript
with open("correlation_heatmap.html", "a") as file:
    file.write(javascript_script)

# Optionally, display the plot in an interactive window (if running in an environment that supports it)
fig.show()