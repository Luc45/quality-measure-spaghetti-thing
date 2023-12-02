import pandas as pd
import seaborn as sns
import matplotlib.pyplot as plt

# Load your CSV file
file_path = '../quality-vs-ratings-out-gpt.csv'  # Replace with your CSV file path
data = pd.read_csv(file_path)

# Calculate the correlation matrix
correlation_matrix = data.corr()

# Generate a heatmap
plt.figure(figsize = (50,50))
sns.heatmap(correlation_matrix, annot=True, cmap='coolwarm')  # 'annot=True' to display the correlation values
plt.title("Correlation Heatmap")

# Save the plot as an image file
plt.savefig("correlation_heatmap.png")

# Optionally, close the plot
plt.close()
