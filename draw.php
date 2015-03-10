<html>
<head>
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>

<script type="text/javascript">
	google.load("visualization", "1", {packages:["corechart"]});
	google.setOnLoadCallback(drawChart);
	function drawChart() {
<?php
	echo "var data_path = \"" . $_GET["datapath"] . "\"; \n";
?>
		var jsonData = $.ajax({
		        url: "getData.php?datapath=" + data_path,
			dataType:"json",
			async: false
			}).responseText;
 
		jsonData = JSON.parse(jsonData);
		// Create our data table out of JSON data loaded from server.
		//var data = new google.visualization.DataTable(jsonData);
 
		document.getElementById("total_time").innerHTML = jsonData['total'];
		document.getElementById("worker_total_time").innerHTML = jsonData['worker_total'];
		document.getElementById("server_total_time").innerHTML = jsonData['server_total'];
		document.getElementById("socket_delay_time").innerHTML = jsonData['socket_delay'];
		document.getElementById("unknown_time").innerHTML = jsonData['unknown_delay'];

		var diagramData = jsonData['diagram'];
		for (i=0;i < diagramData.length; i++) {
			var data = google.visualization.arrayToDataTable(diagramData[i].value);
			var options = { title: diagramData[i].title };
			var divID = 'piechart' + i;

			var chart = new google.visualization.PieChart(document.getElementById(divID));
			chart.draw(data, options);
		}
		var barChart = jsonData['barChart'];
		for (i=0;i < barChart.length; i++) {
			var data = google.visualization.arrayToDataTable(barChart[i].value);
			var options = { title: barChart[i].title, isStacked: barChart[i].isStacked, hAxis: barChart[i].hAxis};
			var divID = 'barchart' + i;

			var chart = new google.visualization.BarChart(document.getElementById(divID));
			chart.draw(data, options);
		}

	}
</script>
</head>
<body>

Total Time: <b id="total_time">waiting...</b> <br>
Worker Total Time: <b id="worker_total_time">waiting...</b> <br>
Server Total Time: <b id="server_total_time">waiting...</b> <br>
Network Delay Time: <b id="socket_delay_time">waiting...</b> <br>
Unknown Time: <font color="red"><b id="unknown_time">waiting...</b></font>

<table>
<tr>
<td> <div id="piechart0" style="width: 700px; height: 400px;"></div> </td>
<td> <div id="piechart1" style="width: 700px; height: 400px;"></div> </td>
</tr>
<tr>
<td> <div id="piechart2" style="width: 700px; height: 400px;"></div> </td>
<td> <div id="piechart3" style="width: 700px; height: 400px;"></div> </td>
</tr>
<tr>
<td> <div id="piechart4" style="width: 700px; height: 400px;"></div> </td>
<td> <div id="piechart5" style="width: 700px; height: 400px;"></div> </td>
</tr>
<tr>
<td> <div id="barchart0" style="width: 700px; height: 400px;"></div> </td>
<td> <div id="barchart1" style="width: 700px; height: 400px;"></div> </td>
</tr>

</table>

</body>
</html>
