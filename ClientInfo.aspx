<%@ Page Language="C#" AutoEventWireup="true" Debug="true"%>
<%@ Import Namespace="System.Web" %>
<%@ Import Namespace="System.Data.OleDb" %>
<%@ Import Namespace="System.Data" %>
<%@ Import Namespace="System.Globalization" %>
<%@ Import Namespace="System.Threading" %>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<script language="c#" runat="server">
    //string database = HttpContext.Current.Server.MapPath("AutoShopDB.mdb");
    string database = "C:\\AutoShopDB\\AutoShopDB.mdb";
    //string database = @"C:\Roger\PrecisionAutoworks\database\AutoShopDB.mdb";
    string success = "Work order submitted successfully, please return the device with your car key to the front desk.";
    string NoRecordFound = "No Record Found";
    private const string AKNOWLEDGE = "I HAVE READ AND UNDERSTOOD THE DISCLAIMERS WHICH FORM PART OF THIS WORKORDER AND AGREE TO BE BOUND THEREBY.";
    private const string COMPANY = "PRECISION AUTOWORKS";
    private string disclaimer()
    {
        StringBuilder s = new StringBuilder();
        string customer = txtFirstName.Text + " " + txtLastName.Text;
        s.Append("I ").Append(customer.ToUpper()).Append(" hereby authorize ").Append(COMPANY)
        .Append(", its agent and employee to perform the above requested service and repairs ")
        .Append("upon and supply the required parts and materials for the above described (vehicle). ")
        .Append("I hereby waive any requirement that any parts removed from the vehicle be returned to me. ")
        .Append("I agree that ").Append(COMPANY).Append(" is not responsible for any loss or damage to vehicle ")
        .Append("or articles left in the vehicle. I herby grant permission to ").Append(COMPANY).Append(" to operate ")
        .Append("the vehicle herein described on streets, highway and elsewhere for the purpose of testing and/or ")
        .Append("inspection. I acknowledge the existance of a mechanic's, garageman's or other possessory lien in favor of ")
        .Append(COMPANY).Append(" on the vehicle in respect of the labour, parts, materials, and services rendered under ")
        .Append("the workorder and the said lien shall continue in force at all time, wether the vehicle in my possession ")
        .Append("or ").Append(COMPANY).Append("'s possession until the account is paid in full. While the vehicle is in my ")
        .Append("possession, it shall at all times be subject to repossession by ").Append(COMPANY).Append(" on demand ")
        .Append("until the account is paid in full.<br/>").Append(AKNOWLEDGE);
        
        return s.ToString();
    }    
	public void Page_Load(object sender, EventArgs e)
	{
        if (!IsPostBack){
            NewCustomer(); 
        }
	}
	void cmdSubmit_OnClick(Object sender, EventArgs e)
    {
        if (!Page.IsValid)
            return;
        
        string ret;
        string sql = BuildSql();
        if(string.IsNullOrEmpty(CustID.Value)) {
            ret = ExecuteNonQuery(sql, true);
        } else { ret = ExecuteNonQuery(sql, false); }
        
        if (!string.IsNullOrEmpty(ret)){
            lblException.Text = ret + " - " + sql.ToString();
        }else if (!string.IsNullOrEmpty(CustID.Value)){                            
            btnAddVehicle.Visible = true;
            btnWorkOrderHistory.Visible = true;
            CreateWorkOrder(CustID.Value, selectedVehicle.Value);
        }
    }
	
	void cmdNewCustomer_OnClick(Object sender, EventArgs e){
        Response.Redirect(Request.RawUrl);   
    }
	void cmdReturningCustomer_OnClick(Object sender, EventArgs e){ 		
        Response.Redirect("default.aspx");
    }
    public void NewCustomer(){
        lblTerms.Text = disclaimer(); 
        btnAddVehicle.Visible = false;
        btnWorkOrderHistory.Visible = false;
        dgCustomerVehicles.DataSource = new DataTable();
        dgCustomerVehicles.DataBind();        
    }
    void FindCustomer(string strSearch, string cid){
        string sql;
        OleDbConnection dbconn = new OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0;Mode=Read;data source=" + database);
        dbconn.Open();
        if (string.IsNullOrEmpty(cid)){
            sql = "select top 1 * from customers where email = '" + strSearch + "' or phone = '" + strSearch + "' or cell = '" + strSearch + "' or CustomerID in(select CustomerID from Customer_Vehicle where ucase(plate) = ucase('" + strSearch + "') and status = 'A')";
        }else { sql = "select top 1 * from customers where CustomerID = " + cid; }
        
        DataTable dt = new DataTable("Customer");
        OleDbDataAdapter adapter = new OleDbDataAdapter(sql, dbconn);
        adapter.Fill(dt);
                
        if (dt.Rows.Count > 0){           
            string CID = dt.Rows[0]["CustomerID"].ToString();
            CustID.Value = CID;
            txtFirstName.Text = dt.Rows[0]["FirstName"].ToString();
            txtLastName.Text = dt.Rows[0]["LastName"].ToString();
            //txtPhone.Text = Regex.Replace(dt.Rows[0]["Phone"].ToString(), @"(\d{3})(\d{3})(\d{4})", "$1-$2-$3");
            //txtCell.Text = Regex.Replace(dt.Rows[0]["Cell"].ToString(), @"(\d{3})(\d{3})(\d{4})", "$1-$2-$3");
            //txtPhone.Text = String.Format("{0:(###) ###-####}", double.Parse(dt.Rows[0]["Phone"].ToString()));
            if (!string.IsNullOrEmpty(dt.Rows[0]["Phone"].ToString())){
                txtPhone.Text = String.Format("{0:###-###-####}", double.Parse(dt.Rows[0]["Phone"].ToString().Replace(" ","")));
            }else { txtPhone.Text = ""; }
            if (!string.IsNullOrEmpty(dt.Rows[0]["Cell"].ToString())){
                txtCell.Text = String.Format("{0:###-###-####}", double.Parse(dt.Rows[0]["Cell"].ToString()));
            }else { txtCell.Text = ""; }            
            txtAddress.Text = dt.Rows[0]["Address"].ToString();
            txtCity.Text = dt.Rows[0]["City"].ToString();
            lstProvince.Text = dt.Rows[0]["Province"].ToString();
            txtEmail.Text = dt.Rows[0]["Email"].ToString();
            txtPostalCode.Text = dt.Rows[0]["PostalCode"].ToString();
            txtPhoneExt.Text = dt.Rows[0]["PhoneExt"].ToString();
            
            sql = "select * from Customer_Vehicle where status='A' and CustomerID = " + CID;
            dt = new DataTable("Customer_Vehicles");
            adapter = new OleDbDataAdapter(sql, dbconn);
            adapter.Fill(dt);
            dgCustomerVehicles.DataSource = dt;
            dgCustomerVehicles.DataBind();

            lblTerms.Text = disclaimer();
            lblInfo.Text = "Welcome back " + txtFirstName.Text.ToString() + " " + txtLastName.Text.ToString();
            btnAddVehicle.Visible = true;
            btnWorkOrderHistory.Visible = true;
            
            // history
            sql = "select Format(w.wo_date, 'dd/mm/yyyy') as wo_date,w.wo_status,v.plate,w.mileage,wo_req1 + ', ' + wo_req2 + ', ' + wo_req3 + ', ' + wo_req4 + ', ' + wo_req5 + ', ' + wo_note as [Details],Customer_Note from work_order w, Customer_Vehicle v where v.CustomerID = w.CustomerID and v.CVID = w.CVID and w.CustomerID = " + CID + " order by wo_date desc";
            dt = new DataTable("work_orders");
            adapter = new OleDbDataAdapter(sql, dbconn);
            adapter.Fill(dt);
            dgHistory.DataSource = dt;
            dgHistory.DataBind();
        }else{
            Page.ClientScript.RegisterStartupScript(this.GetType(), "alert", "NoRecordFound();", true);
            //Response.Redirect(Request.RawUrl);            
        }
                      
        dbconn.Close();
        dt.Dispose();
        adapter.Dispose();
        dbconn.Dispose();
    }
    
    protected string BuildSql()
    {
        StringBuilder sql = new StringBuilder();
        CultureInfo cultureInfo = Thread.CurrentThread.CurrentCulture;
        TextInfo textInfo = cultureInfo.TextInfo;
        int ext;
        bool isInteger = int.TryParse(txtPhoneExt.Text, out ext);
        if (string.IsNullOrEmpty(CustID.Value))
        {
            sql.Append("insert into customers (FirstName,LastName,phone,cell,email,address,city,province,PostalCode,PhoneExt) values('")
                .Append(textInfo.ToTitleCase(txtFirstName.Text.Replace("'","''"))).Append("','")
                .Append(textInfo.ToTitleCase(txtLastName.Text.Replace("'","''"))).Append("','")
                .Append(txtPhone.Text.Replace("-", "").Replace("(", "").Replace(")", "").Replace(" ", "")).Append("','")
                .Append(txtCell.Text.Replace("-", "").Replace("(", "").Replace(")", "").Replace(" ", "")).Append("','")
                .Append(txtEmail.Text).Append("','")
                .Append(txtAddress.Text).Append("','")
                .Append(textInfo.ToTitleCase(txtCity.Text)).Append("','")
                .Append(lstProvince.Text).Append("','")
                .Append(txtPostalCode.Text.ToUpper());
            if (isInteger == true){
                sql.Append("',").Append(txtPhoneExt.Text).Append(")");                      
            } else{
                sql.Append("',null)");
            }
        }else{
            sql.Append("update customers set customers.FirstName = '")
            .Append(textInfo.ToTitleCase(txtFirstName.Text.Replace("'","''")))
            .Append("', customers.LastName = '")
            .Append(textInfo.ToTitleCase(txtLastName.Text.Replace("'","''")))
            .Append("', customers.Phone = '")
            .Append(txtPhone.Text.Replace("-","").Replace("(","").Replace(")","").Replace(" ",""))
            .Append("', customers.Cell = '")
            .Append(txtCell.Text.Replace("-", "").Replace("(", "").Replace(")", "").Replace(" ",""))
            .Append("', customers.Email = '")
            .Append(txtEmail.Text)
            .Append("', customers.Address = '")
            .Append(txtAddress.Text)
            .Append("', customers.City = '")
            .Append(textInfo.ToTitleCase(txtCity.Text))
            .Append("', customers.Province = '")
            .Append(lstProvince.Text)
            .Append("', customers.PostalCode = '")
            .Append(txtPostalCode.Text.ToUpper());
            if (isInteger == true){
                sql.Append("', PhoneExt = ").Append(txtPhoneExt.Text);
            }
            else{
                sql.Append("', PhoneExt = null ");
            }
            sql.Append(" where customers.CustomerID = ").Append(CustID.Value);    
        }        
        return sql.ToString();
    }
    protected string ExecuteNonQuery(string sql, Boolean identity)
    {
        string ret = null;
        OleDbConnection con; 
        OleDbCommand cmd; 
        try{
            con = new OleDbConnection("Provider=Microsoft.Jet.OLEDB.4.0; Data Source=" + database);
            con.Open();
            cmd = new OleDbCommand(sql, con);
            cmd.ExecuteNonQuery();
            
            if(identity){
                cmd.CommandText = "SELECT @@identity";
                CustID.Value = cmd.ExecuteScalar().ToString();
            }
            con.Close();             
            cmd.Dispose();
            con.Dispose();
        }catch(Exception ex){
            ret = ex.Message;
        }finally{
            cmd = null;
            con = null;
        }
        return ret;
    }
        
    protected void Check_CheckedChanged(object sender, EventArgs e)
    {
        RadioButton rb = (sender as RadioButton);
        if (rb.Checked){
            txtRequiredWork.Enabled = true;
            txtNote.Enabled = true;
            selectedVehicle.Value = rb.ToolTip;
        }

        foreach (DataGridItem row in dgCustomerVehicles.Items){
            RadioButton RadioButton = row.FindControl("Check") as RadioButton;
            if ((!object.ReferenceEquals(RadioButton, rb))){
                RadioButton.Checked = false;
            }            
        }
        //txtRequiredWork.Text = rb.ClientID + " - " + selectedVehicle.Value + " - " + CustID.Value;
    }

    protected void AlterTable()
    {
        //string sql = "ALTER TABLE customer_vehicle DROP COLUMN Notes";
        string sql = "ALTER TABLE customer_vehicle ADD COLUMN Status CHAR(1) DEFAULT 'A' NOT NULL;";
        string ret = ExecuteNonQuery(sql, false);
        txtRequiredWork.Text = ret + " - " + sql;
    }
    protected void btnDelete_Click(object sender, ImageClickEventArgs e)
    {
        ImageButton img = (sender as ImageButton);
        string vid = img.CommandArgument;
        string sql = "update customer_vehicle set status = 'I' where CVID = " + vid;
        string ret = ExecuteNonQuery(sql, false);
        if (string.IsNullOrEmpty(ret)) {
            FindCustomer("", CustID.Value);
        }else { txtRequiredWork.Text = ret; } // img.ClientID + " " + img.CommandArgument;
    }
    protected void btnSearch_Click(object sender, EventArgs e)
    {
        if (!string.IsNullOrEmpty(txtsearch.Text))
            FindCustomer(txtsearch.Text,"");
    }

    protected void btnSaveVehicle_Click(object sender, EventArgs e)
    {
        lblInfo.Text = CustID.Value + " - SaveVehicle";
        StringBuilder sql = new StringBuilder();
        sql.Append("insert into customer_vehicle([CustomerID],[Plate],[Make],[Model],[Year],[Color],[Status]) values(")
            .Append(CustID.Value).Append(",'")
            .Append(txtPlate.Text.ToUpper()).Append("','")
            .Append(txtMake.Text.ToUpper()).Append("','")
            .Append(txtModel.Text.ToUpper()).Append("','")
            .Append(txtYear.Text).Append("','")
            .Append(txtColor.Text.ToUpper()).Append("','A')");
        string ret = ExecuteNonQuery(sql.ToString(), false);
        if (!string.IsNullOrEmpty(ret)) { 
            lblException.Text = ret;
        }
        FindCustomer("", CustID.Value);
    }
    
    protected void CreateWorkOrder(string customerid, string vehicleid)
    {
        if ( (!string.IsNullOrEmpty(customerid)) & (!string.IsNullOrEmpty(vehicleid)) )
        {
            StringBuilder sql = new StringBuilder();
            string s = txtRequiredWork.Text.Replace("'","''");
            string[] RequiredWorks = s.Split(new char[] { ',' }, 6);
            sql.Append("insert into work_order([CustomerID],[CVID],[WO_Date],[WO_Status],[Priority],[checksum],[WO_Req1],[WO_Req2],[WO_Req3],[WO_Req4],[WO_Req5],[WO_Note],[Customer_Note]) values(")
                .Append(customerid).Append(",").Append(vehicleid).Append(",NOW(),'NEW','NORMAL',0,'");
            Int32 n = RequiredWorks.Length;
            if (n < 1)
            {
                sql.Append("','','','','','").Append(s).Append("','");
            }
            else
            {
                if (n > 0)
                {
                    sql.Append(RequiredWorks[0]).Append("','");
                }
                else { sql.Append("','"); }
                if (n > 1)
                {
                    sql.Append(RequiredWorks[1].Trim()).Append("','");
                }
                else { sql.Append("','"); }
                if (n > 2)
                {
                    sql.Append(RequiredWorks[2].Trim()).Append("','");
                }
                else { sql.Append("','"); }
                if (n > 3)
                {
                    sql.Append(RequiredWorks[3].Trim()).Append("','");
                }
                else { sql.Append("','"); }
                if (n > 4)
                {
                    sql.Append(RequiredWorks[4].Trim()).Append("','");
                }
                else { sql.Append("','"); }
                if (n > 5)
                {
                    sql.Append(RequiredWorks[5].Trim()).Append("','");
                }
                else { sql.Append("','"); }
            }
            sql.Append(txtNote.Text.Replace("'", "''")).Append("')");
            string ret = ExecuteNonQuery(sql.ToString(), false);
            if (string.IsNullOrEmpty(ret)) {        
                Page.ClientScript.RegisterStartupScript(this.GetType(), "alert", "OrderComplete();", true);
                lblInfo.Text = success;
            }
            else { lblException.Text = ret; };            
        }
        
    }
    //private void ShowPopUpMsg(string msg)
    //{
    //    StringBuilder sb = new StringBuilder();
    //    sb.Append("alert('");
    //    sb.Append(msg.Replace("\n", "\\n").Replace("\r", "").Replace("'", "\\'"));
    //    sb.Append("');");
    //    ScriptManager.RegisterStartupScript(this.Page, this.GetType(), "showalert", sb.ToString(), true);
    //}
    
</script>

<head runat="server">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>AutoShop Client</title>
    <script type="text/javascript" src="jquery-1.4.3.min.js"></script>     
    <script language="javascript" type="text/javascript">
        function getinfo() {
            var ret = window.prompt('Please enter your phone number', '6139992222');
        }
        function OrderComplete() {            
            alert("<%=success%>");
            document.getElementById("cmdNewCustomer").click();
        }
        function NoRecordFound() { alert("No Record Found!");}
        function ShowDialog() { $("#overlay").show(); $("#dialog").fadeIn(50); document.getElementById("txtSearch").focus(); dlgVisible('1'); }
        function HideDialog() { $("#overlay").hide(); $("#dialog").fadeOut(5); dlgVisible('0');; }
        function ShowDialogVehicle() { $("#overlay").show(); $("#dlgVehicle").fadeIn(50); document.getElementById("txtPlate").focus(); dlgVisible('1'); }
        function HideDialogVehicle() { $("#overlay").hide(); $("#dlgVehicle").fadeOut(5); dlgVisible('0'); }
        function ShowDialogHistory() { $("#overlay").show(); $("#dlgHistory").fadeIn(50); dlgVisible('1'); }
        function HideDialogHistory() { $("#overlay").hide(); $("#dlgHistory").fadeOut(5); dlgVisible('0'); }
        function dlgVisible(val) { document.getElementById("dialogVisible").value = val; }

        function validNumber(evt) {
            var charCode = (evt.which) ? evt.which : event.keyCode
            if (charCode > 31 && (charCode < 48 || charCode > 57))
                return false;
            return true;
        }
        function cancelBack() {
            if ((event.keyCode == 8 || (event.keyCode == 37 && event.altKey) || (event.keyCode == 39 && event.altKey))
                && (event.srcElement.form == null || event.srcElement.isTextEdit == false)) {
                event.cancelBubble = true;
                event.returnValue = false;
            }
        }
        function isValidForm() {
            if (document.getElementById("dialog").style.display == 'none' && document.getElementById("dlgVehicle").style.display == 'none' || document.getElementById("dialogVisible").value == '0') {
                if (document.getElementById('txtFirstName').value == "") {
                    alert("First Name is a mandatory field");
                    document.getElementById('txtFirstName').style.background = "#87CEFA";
                    document.getElementById('txtFirstName').focus();
                    return false;
                } else if (document.getElementById('txtPhone').value == "" && document.getElementById('txtCell').value == "" && document.getElementById('txtEmail').value == "") {
                    alert("At least one contact information is required. \n Please enter Phone, cell, or email to continue.");
                    document.getElementById('txtPhone').style.background = "#87CEFA";
                    document.getElementById('txtCell').style.background = "#87CEFA";
                    document.getElementById('txtEmail').style.background = "#87CEFA";
                    return false;                                    
                } else {                    
                    return isTermAccepted();
                }
            }
            else { return true; }
        }
	function confirmDelete(plate) {
            return confirm('Are you sure you want to delete this vehicle? ' + plate);
        }
        //onclick="this.form.reset(); return false;"
	function isTermAccepted()
	{
	    if (document.getElementById('txtRequiredWork').disabled) {	        
	        return true;
	    }else{
	        var terms = document.getElementById('AcceptTerms');
	        if (!terms.checked) {
	            alert("Please accept the terms... ");
	            document.getElementById('AcceptTerms').focus();
	            return false;
	        }
	        else { return true; }
	    }
	}
	function HandleBackFunctionality() {
	    if (window.event.clientX < 40 && window.event.clientY < 0) {
	        window.history.go(0);
	    }
	}
    </script>
</head>
<body onkeydown="cancelBack()" onbeforeunload="HandleBackFunctionality()">
<div style="height:760px;width:620px;border-radius:25px;padding:7px;border-style:outset;">
	<img src="Header.jpg" style="padding-left:0px; padding-bottom:0px; width:620px;" alt="Precision Autoworks"/>
	<form id="ClientForm" runat="server" defaultbutton="btnSearch" defaultfocus="txtSearch" onsubmit="return isValidForm()"> 
        <input type="hidden" id="CustID" value="" runat="server" />
        <input type="hidden" id="selectedVehicle" value="" runat="server" />
        <input type="hidden" id="dialogVisible" value="0" />
        <asp:Panel runat="server" HorizontalAlign="Center">            
		    <asp:button id="cmdNewCustomer" text="New Customer" OnClick="cmdNewCustomer_OnClick" runat="server" style="height:30px;" onmouseover="this.style.color='navy';this.style.cursor='hand';" onmouseout="this.style.color='black';"></asp:button>
		    <button id="cmdReturningCustomer" onclick="javascript:ShowDialog();" style="margin-left:4px; height:30px;" onmouseover="this.style.color='navy';this.style.cursor='hand';" onmouseout="this.style.color='black';">Returning Customer</button>
		    <asp:button id="cmdSubmit" text="Save / Submit" OnClick="cmdSubmit_OnClick" runat="server" style="margin-left:4px; height:30px; width:145px;" onmouseover="this.style.color='navy';this.style.cursor='hand';" onmouseout="this.style.color='black';"></asp:button>
            <%--<button id="cmdClear" type="reset" onclick='javascript:document.getElementById("cmdNewCustomer").click();' style="margin-left:2px; height:35px;" onmouseover="this.style.color='navy';this.style.cursor='hand';" onmouseout="this.style.color='black';">Clear Work Order</button>--%>
		    <%--<asp:button id="cmdClear" text="Clear Work Order" OnClick="cmdNewCustomer_OnClick" runat="server" style="margin-left:2px; height:35px;" onmouseover="this.style.color='navy';this.style.cursor='hand';" onmouseout="this.style.color='black';"></asp:button>--%>        
        </asp:Panel>         
        
        <table style="width:620px; margin-top:4px;">
		<tr>
		    <td width="70"><asp:Label runat="server" font-size="small" Width="70px">First Name:</asp:Label></td>
		    <td><asp:TextBox id="txtFirstName" text="" runat="server" width="190" font-size="small" tabindex="1" MaxLength="40"></asp:TextBox></td>
		    <td width="18">&nbsp;</td>
            <td width="70"><asp:Label runat="server" font-size="small" Width="70px">Email:</asp:Label></td>
            <td width="260"><asp:TextBox id="txtEmail" text="" runat="server" width="170" font-size="small" tabindex="6"></asp:TextBox>
                <asp:RegularExpressionValidator ID="validateEmail" runat="server" ErrorMessage="Invalid" ControlToValidate="txtEmail" ForeColor="Red"  Font-Size="Small" ValidationExpression="^([\w\.\-]+)@([\w\-]+)((\.(\w){2,3})+)$" />
            </td>		    
		</tr>
		<tr>
		    <td width="70"><asp:Label runat="server" font-size="small" Width="70px">Last Name:</asp:Label></td>
		    <td><asp:TextBox id="txtLastName" text="" runat="server" width="190" font-size="small" tabindex="2" MaxLength="40"></asp:TextBox></td>
		    <td width="18">&nbsp;</td>
		    <td width="70"><asp:Label runat="server" font-size="small" Width="70px">Phone:</asp:Label></td>
		    <td ><asp:TextBox id="txtPhone" text="" runat="server" width="112" font-size="small" tabindex="7" MaxLength="14"></asp:TextBox>                
		        <asp:Label runat="server" font-size="small">Ext:</asp:Label>
                <asp:TextBox runat="server" ID="txtPhoneExt" MaxLength="5" Width="35"></asp:TextBox>
                <asp:RegularExpressionValidator ID="reg1" runat="server" ControlToValidate="txtPhone" SetFocusOnError="true" ErrorMessage="Invalid" ForeColor="Red" Font-Size="Small" ValidationExpression="^(\([0-9]{3}\)|[0-9]{3})[ -\.]?[0-9]{3}[ -\.]?[0-9]{4}$"></asp:RegularExpressionValidator>
            </td>
		</tr>
		<tr>
		    <td width="70"><asp:Label runat="server" font-size="small" Width="70px">Address:</asp:Label></td>
		    <td><asp:TextBox id="txtAddress" text="" runat="server" width="190" font-size="small" tabindex="3"></asp:TextBox></td>
		    <td width="18">&nbsp;</td>
		    <td width="70"><asp:Label runat="server" font-size="small" Width="70px">Cell:</asp:Label></td>
		    <td><asp:TextBox id="txtCell" text="" runat="server" width="112" font-size="small" tabindex="8" MaxLength="14"></asp:TextBox>
                <asp:RegularExpressionValidator ID="reg2" runat="server" ControlToValidate="txtCell" SetFocusOnError="true" ErrorMessage="Invalid" ForeColor="Red" Font-Size="Small" ValidationExpression="^(\([0-9]{3}\)|[0-9]{3})[ -\.]?[0-9]{3}[ -\.]?[0-9]{4}$"></asp:RegularExpressionValidator>
		    </td>   		
		</tr>
		<tr>
		<td width="70"><asp:Label runat="server" font-size="small" Width="70px">City:</asp:Label></td>
		<td><asp:TextBox id="txtCity" text="Ottawa" runat="server" width="190" font-size="small" tabindex="4"></asp:TextBox></td>
		<td width="18">&nbsp;</td>
		<td width="70"><asp:Label runat="server" font-size="small" Width="70px">Postal Code:</asp:Label></td>
		<td width="190"><asp:TextBox id="txtPostalCode" text="" runat="server" width="90px" font-size="small" tabindex="9"></asp:TextBox>
            <asp:RegularExpressionValidator ID="REXZipCode" runat="server" ControlToValidate="txtPostalCode" ErrorMessage="Invalid" Font-Size="Small" ForeColor="Red" ValidationExpression="^[ABCEGHJKLMNPRSTVXYabceghjklmnprstvxy]{1}\d{1}[A-Za-z]{1} *\d{1}[A-Za-z]{1}\d{1}$" />
		</td>
		</tr>
		<tr>
		<td><asp:Label runat="server" font-size="small" Width="70px">Province: </asp:Label></td>
		<td><asp:DropDownList id="lstProvince" runat="server" width="190" font-size="small" tabindex="5">
				<asp:ListItem Text="Ontario" Value="Ontario" /> 
				<asp:ListItem Text="Quebec" Value="Quebec" /> 
				<asp:ListItem Text="Nova Scotia" Value="Nova Scotia" /> 
				<asp:ListItem Text="New Brunswick" Value="New Brunswick" /> 
				<asp:ListItem Text="Manitoba" Value="Manitoba" /> 
				<asp:ListItem Text="British Columbia" Value="British Columbia" /> 
				<asp:ListItem Text="Prince Edward Island" Value="Prince Edward Island" /> 
				<asp:ListItem Text="Saskatchewan" Value="Saskatchewan" /> 
				<asp:ListItem Text="Alberta" Value="Alberta" /> 
				<asp:ListItem Text="Newfoundland and Labrador" Value="Newfoundland and Labrador" /> 
			</asp:DropDownList>
		</td>
		<td width="18">&nbsp;</td>		
		<td colspan="2" align="right">
            <button id="btnAddVehicle" runat="server" onclick="javascript:ShowDialogVehicle();document.getElementById('txtPlate').value='';" style="width:140px;height:24PX;" onmouseover="this.style.color='navy';" onmouseout="this.style.color='black';">Add Vehicle</button>&nbsp;
            <button id="btnWorkOrderHistory" runat="server" style="width:140px;height:24PX; margin-right:8px;" onclick="javascript:ShowDialogHistory();" onmouseover="this.style.color='navy';" onmouseout="this.style.color='black';">History</button>
		</td>
		</tr>
        <tr>
            <td colspan="5">
                <label style="font-size:small; font-style:italic;">Please select vehicle which you creating work order</label>
                <asp:DataGrid id="dgCustomerVehicles" runat="server" AutoGenerateColumns="false" HeaderStyle-BackColor="LightSkyBlue" HeaderStyle-Font-Size="Small" ShowHeader="true" Width="610px" ShowFooter="true">
                    <Columns>
                        <%--<asp:EditCommandColumn EditText="Edit" CancelText="Cancel" UpdateText="Update" ItemStyle-Font-Size="Small"/>--%>
                        <asp:TemplateColumn>
                            <ItemTemplate>
                                <asp:ImageButton ID="btnDelete" runat="server" ImageUrl="delete.png" CommandName="Delete" OnClick="btnDelete_Click" ToolTip="Remove vehicle from list" CommandArgument='<%# DataBinder.Eval(Container.DataItem, "CVID") %>' OnClientClick='<%# String.Format("return confirmDelete(\"{0}\");", Eval("Plate")) %>'/>
                            </ItemTemplate>
                        </asp:TemplateColumn>
                        <asp:TemplateColumn HeaderText="Check" HeaderStyle-Width="40">
                            <ItemTemplate>
                                <asp:RadioButton runat="server" ID="Check" OnCheckedChanged="Check_CheckedChanged" AutoPostBack="true" ToolTip='<%# DataBinder.Eval(Container.DataItem, "CVID") %>'/>                                
                            </ItemTemplate>
                            <EditItemTemplate>                                
                            </EditItemTemplate>
                        </asp:TemplateColumn>
                        <asp:BoundColumn DataField="CVID" HeaderText="ID" ReadOnly="True" ItemStyle-Font-Size="Small" ItemStyle-Width="25px"/>
                        <asp:BoundColumn DataField="Plate" HeaderText="Plate" ReadOnly="True" ItemStyle-Font-Size="Small" ItemStyle-Width="80px"/>
                        <asp:BoundColumn DataField="Make" HeaderText="Make" ReadOnly="True" ItemStyle-Font-Size="Small" ItemStyle-Width="100px"/>
                        <asp:BoundColumn DataField="Model" HeaderText="Model" ReadOnly="True" ItemStyle-Font-Size="Small" ItemStyle-Width="100px"/>
                        <asp:BoundColumn DataField="Year" HeaderText="Year" ReadOnly="True" ItemStyle-Font-Size="Small" ItemStyle-Width="55px"/>
                        <asp:BoundColumn DataField="Color" HeaderText="Color" ReadOnly="True" ItemStyle-Font-Size="Small" ItemStyle-Width="100px"/>
                        <asp:BoundColumn DataField="Vin" HeaderText="Vin" ReadOnly="True" ItemStyle-Font-Size="Small" ItemStyle-Width="200px" />                
                    </Columns>

                </asp:DataGrid>
            </td>
        </tr>
		<tr>
		<td colspan="5">
            <label style="padding-top:10px;font-size:small">Required Work: </label><label style="font-size:small;font-style:italic; padding-left:6px;">Please enter required work comma seperate</label>
            <asp:TextBox runat="server" id="txtRequiredWork" TextMode="multiline" rows="4" width="596px" font-size="small" TabIndex="10" Enabled="false"></asp:TextBox>
		</td>
		</tr>
		<tr>		
		<td colspan="5">
            <label style="font-size:small">Note: </label><label style="font-size:small;font-style:italic; padding-left:6px;">Please enter any extra note</label>
            <asp:TextBox runat="server" id="txtNote" TextMode="multiline" rows="3" width="596px" font-size="small" TabIndex="11" Enabled="false"></asp:TextBox>
		</td>
		</tr>
		</table>
                        
        <div style="background-color:lightpink; min-height:30px; width:610px; font-size:x-small;">                                      
            <asp:CheckBox runat="server" ID="AcceptTerms" ClientIDMode="Static"/>           
            <asp:Label runat="server" ID="lblTerms" Text=""></asp:Label>            
        </div>
        <br />
        				
        <asp:label id="lblInfo" runat="server" Font-Size="Small">.</asp:label>
        <br />
        <asp:label id="lblException" runat="server" ForeColor="Maroon" Font-Size="Small"></asp:label>
        <br /><br />
        
        <div style="justify-content:center; text-align:center; font-size:Small;"><footer>&copy; 2015 Precision Autoworks</footer></div>
        
        <div id="dialog" style="display:none;width:260px;height:120px;margin-left:140px;margin-top:-420px;background-color: #ffffff;border:2px solid #336699;padding:0px;z-index:101;">
            <table style="width:260px;border:0px;">
                <tr style="border-bottom:solid 2px #336699;background-color:#535657;padding:4px;color:White;font-weight:bold;width:260px;height:5px;">
                    <td style="width:240px;">AutoShop - Search</td>                
                    <td style="color:White;text-decoration:none; text-align:right; width:12px;"><a href="javascript:HideDialog();" id="btnClose" style="color:#ffffff;" onmouseover="this.style.color='red';" onmouseout="this.style.color='white';">Close</a></td>                
                </tr>     
                <tr>
                    <td colspan="3" style="width:280px;">
                        <div style="padding-left:10px; padding-top:10px;">
                            <asp:Label runat="server" Font-Size="Small">Please enter phone, cell, email, or plate#</asp:Label><br />
                            <asp:TextBox runat="server" id="txtsearch" style="width:220px;height:20px;margin:2px;"/>
                            <br />
                            <center>
                            <asp:Button runat="server" ID="btnSearch" Text="Find" Width="100px" Height="30px" Font-Size="Small" OnClick="btnSearch_Click"/>
                            </center>
                        
                        </div>
                    </td>                
                </tr>   
            </table>                       
        </div>

         <div id="dlgVehicle" style="display:none;width:260px;height:240px;margin-left:140px;margin-top:-420px;background-color: #ffffff;border:2px solid #336699;padding:0px;z-index:101;">
            <table style="width:260px;border:0px;" cellpadding="0" cellspacing="0">
                <tr style="border-bottom:solid 2px #336699;background-color:#535657;padding:4px;color:White;font-weight:bold;width:260px;height:5px;">
                    <td style="width:240px;">AutoShop - Add Vehicle</td>                
                    <td style="color:White;text-decoration:none; text-align:right; width:12px;"><a href="javascript:HideDialogVehicle();" id="btnClose1" style="color:#ffffff;">Close</a></td>                
                </tr>     
                <tr>
                    <td colspan="2" style="width:260px;">
                        <div style="padding-left:10px; padding-top:10px;">
                            <table>
                                <tr>
                                    <td>
                                        <asp:Label runat="server" Font-Size="Small">Plate</asp:Label></td>
                                    <td>
                                        <asp:TextBox runat="server" ID="txtPlate" Style="width:140px; margin:2px;" Font-Size="Small" MaxLength="9" Text="required" ClientIDMode="Static" />
                                        <asp:RequiredFieldValidator ID="ReqPlate" runat="server" ControlToValidate="txtPlate" Font-Size="Small" ForeColor="Red" ErrorMessage="Required" SetFocusOnError="true"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><asp:Label runat="server" Font-Size="Small">Make</asp:Label></td>
                                    <td><asp:TextBox runat="server" id="txtMake" style="width:140px;margin:2px;" Font-Size="Small" MaxLength="20"/></td>
                                </tr>
                                <tr>
                                    <td><asp:Label runat="server" Font-Size="Small">Model</asp:Label></td>
                                    <td><asp:TextBox runat="server" id="txtModel" style="width:140px;margin:2px;" Font-Size="Small" MaxLength="20"/></td>
                                </tr>
                                <tr>
                                    <td><asp:Label runat="server" Font-Size="Small">Year</asp:Label></td>
                                    <td><asp:TextBox runat="server" id="txtYear" style="width:140px;margin:2px;" Font-Size="Small" MaxLength="4"/>
                                        <asp:CompareValidator ID="CompareValidator1" runat="server" ControlToValidate="txtYear" Type="Integer" ErrorMessage="Invalid" ForeColor="Red" Operator="DataTypeCheck" Font-Size="Small" SetFocusOnError="true"></asp:CompareValidator>
                                    </td>
                                </tr>
                                <tr>
                                    <td><asp:Label runat="server" Font-Size="Small">Color</asp:Label></td>
                                    <td><asp:TextBox runat="server" id="txtColor" style="width:140px;margin:2px;" Font-Size="Small" MaxLength="20"/></td>
                                </tr>
                            </table>                                                                                                                                                                      
                            <center>
                            <asp:Button runat="server" ID="btnSaveVehicle" Text="Save Vehicle" Width="100px" Font-Size="Small" OnClick="btnSaveVehicle_Click"/>
                            </center>
                        
                        </div>
                    </td>                
                </tr>   
            </table>                       
        </div>
        
        <div id="dlgHistory" style="display:none;width:540px;height:300px;margin-left:15px;margin-top:-520px;background-color: #ffffff;border:2px solid #336699;padding:0px;z-index:107;">
            <table style="width:540px;border:0px;" cellpadding="0" cellspacing="0">
                <tr style="border-bottom:solid 2px #336699;background-color:#535657;padding:4px;color:White;font-weight:bold;width:520px;height:5px;">
                    <td style="width:510px;">AutoShop - Vehicle Work History</td>                
                    <td style="color:White;text-decoration:none; text-align:right; width:12px;"><a href="javascript:HideDialogHistory();" id="btnCloseHistory" style="color:#ffffff;">Close</a></td>                
                </tr>     
                <tr>
                    <td colspan="3" style="width:540px;">
                        <div style="OVERFLOW:auto;width:540px;height:300px">
                        <asp:DataGrid id="dgHistory" runat="server" AutoGenerateColumns="false" HeaderStyle-BackColor="LightSkyBlue" HeaderStyle-Font-Size="X-Small" ShowHeader="true" Width="540px" ShowFooter="true" BackColor="Wheat">
                        <Columns>                                               
                        <asp:BoundColumn DataField="wo_date" HeaderText="Date" ReadOnly="True" ItemStyle-Font-Size="x-Small" ItemStyle-Width="100px"/>
                        <asp:BoundColumn DataField="wo_status" HeaderText="Status" ReadOnly="True" ItemStyle-Font-Size="X-Small" ItemStyle-Width="80px"/>
                        <asp:BoundColumn DataField="Plate" HeaderText="Plate" ReadOnly="True" ItemStyle-Font-Size="X-Small" ItemStyle-Width="80px"/>
                        <asp:BoundColumn DataField="Mileage" HeaderText="Mileage" ReadOnly="True" ItemStyle-Font-Size="X-Small" ItemStyle-Width="90px"/>
                        <asp:BoundColumn DataField="Details" HeaderText="Details" ReadOnly="True" ItemStyle-Font-Size="X-Small" ItemStyle-Width="320px"/>
                        <asp:BoundColumn DataField="Customer_Note" HeaderText="Note" ReadOnly="True" ItemStyle-Font-Size="X-Small" ItemStyle-Width="300px"/> 
                        </Columns>
                        </asp:DataGrid>
                      </div> 
                    </td>                                   
                </tr>   
            </table>                       
        </div>    
	</form>  
    </div>
   <br />
   <br />   
</body>
</html>