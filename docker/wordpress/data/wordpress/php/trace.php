<html>
<body>
<h1>Tracing</h1>
    <table>
        <tr>
            <th>Trace Id:</th>
            <td><?php echo elastic_apm_get_trace_id(); ?></td>
        </tr>
        <tr>
            <th>Transaction Id:</th>
            <td><?php echo elastic_apm_get_transaction_id(); ?></td>
        </tr>
    </table>
</body>
</html>
