<!DOCTYPE html>
<html>
<head>
<title>Arduino - Arm Robot - Web</title>
<meta name="viewport" content="width=device-width, initial-scale=0.9">
<style>
body { text-align: center; }
canvas { background-color: #FFFFFF; }
</style>
<script>
var MOTOR_1 = 0;
var MOTOR_2 = 1;
var MOTOR_3 = 2;
var MOTOR_4 = 3;
var MOTOR_5 = 4;
var MOTOR_6 = 5;

var ARM_LENGTH_1 = 95;
var ARM_LENGTH_2 = 88;
var ARM_LENGTH_3 = 155;

var SOLE_WIDTH = 160;
var SOLE_HEIGHT = 73;

var flywheel_img = new Image();
flywheel_img.src = "flywheel.png";

var zone_A_radius = ARM_LENGTH_1 + ARM_LENGTH_2 + ARM_LENGTH_3;

var pivot_x = zone_A_radius + 5;
var pivot_y = zone_A_radius + 5;

var zone_B_radius = 100;
var zone_B_center_x = pivot_x / 2;
var zone_B_center_y = -(zone_B_radius + SOLE_HEIGHT + 20);
var zone_B_last_angle = 0;

var zone_C_radius = 70;
var zone_C_center_x = -pivot_x / 2;
var zone_C_center_y = -(zone_B_radius + SOLE_HEIGHT + 20);
var zone_C_last_angle = 0;

var zone_D_width = 30;
var zone_D_height = 200.0;
var zone_D_center_x = 0;
var zone_D_center_y = -(zone_B_radius + SOLE_HEIGHT + 110);
var grip_height = 70;
var grip_width = 100;

var canvas_width = zone_A_radius * 2 + 10;
var canvas_height = zone_A_radius  + SOLE_HEIGHT + zone_B_radius * 2 + 180;

var grip_max_angle = 62.0; // 62 degree

var click_state = 0;
var mouse_xyra = {x:0, y:0, r:0.0, a:0.0};
var angles = [90, 90, 180, 180, 90, 17];

var ws = null;
var servo = null, ctx = null;

var last_time;

var a = 0, b = 0, c = 0;

function init()
{
    servo = document.getElementById("servo");
    servo.width = canvas_width;
    servo.height = canvas_height;

    servo.addEventListener("touchstart", mouse_down);
    servo.addEventListener("touchend", mouse_up);
    servo.addEventListener("touchmove", mouse_move);
    servo.addEventListener("mousedown", mouse_down);
    servo.addEventListener("mouseup", mouse_up);
    servo.addEventListener("mousemove", mouse_move);

    ctx = servo.getContext("2d");

    ctx.translate(pivot_x, pivot_y);
    ctx.rotate(Math.PI);

    // quadratic equation parameters
    a = 4*ARM_LENGTH_1*ARM_LENGTH_3;
    b = 2*(ARM_LENGTH_2*ARM_LENGTH_1 + ARM_LENGTH_2*ARM_LENGTH_3);
    c = Math.pow(ARM_LENGTH_1, 2) + Math.pow(ARM_LENGTH_2, 2)  + Math.pow(ARM_LENGTH_3, 2) - 2*ARM_LENGTH_1*ARM_LENGTH_3;
}
function update_view()
{
    ctx.clearRect(-canvas_width/2, -SOLE_HEIGHT, canvas_width, canvas_height);
    ctx.save();

    //draw zone A
    ctx.fillStyle = "#D9D9D9";
    ctx.beginPath();
    ctx.arc(0,0,zone_A_radius, 0, 2*Math.PI);
    ctx.fill();

    ctx.fillStyle = "#FFFFFF";
    ctx.fillRect(-pivot_x , -canvas_height + pivot_y, canvas_width, canvas_height - pivot_y - SOLE_HEIGHT);

    // draw arm segments
    ctx.strokeStyle="#00979D";
    ctx.fillStyle = "#FF4500";
    ctx.lineWidth = 20;
    ctx.rotate(angles[MOTOR_2] / 180 * Math.PI);
    ctx.beginPath();
    ctx.moveTo(0,0);
    ctx.lineTo(ARM_LENGTH_1,0);
    ctx.stroke();
    draw_pivot(0, 0);

    ctx.translate(ARM_LENGTH_1,0);
    ctx.rotate(-Math.PI + angles[MOTOR_3] / 180 * Math.PI);
    ctx.beginPath();
    ctx.moveTo(0,0);
    ctx.lineTo(ARM_LENGTH_2, 0);
    ctx.stroke();
    draw_pivot(0, 0);

    ctx.translate(ARM_LENGTH_2,0);
    ctx.rotate(-Math.PI + angles[MOTOR_4] / 180 * Math.PI);
    ctx.beginPath();
    ctx.moveTo(0,0);
    ctx.lineTo(ARM_LENGTH_3, 0);
    ctx.stroke();
    draw_pivot(0, 0);

    ctx.restore();

    ctx.strokeStyle = "#00979D";
    ctx.lineWidth = 6;

    // draw zone B
    angle = (angles[MOTOR_1] + 45) * Math.PI / 180;
    ctx.save();
    ctx.translate(zone_B_center_x, zone_B_center_y);
    ctx.rotate(angle);
    ctx.drawImage(flywheel_img, - zone_B_radius, - zone_B_radius, zone_B_radius * 2, zone_B_radius * 2);
    ctx.beginPath();
    ctx.arc(0, 0, zone_B_radius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.restore();

    // draw zone C
    angle = (angles[MOTOR_5] + 45) * Math.PI / 180;
    ctx.save();
    ctx.translate(zone_C_center_x, zone_C_center_y);
    ctx.rotate(angle);
    ctx.drawImage(flywheel_img, - zone_C_radius, - zone_C_radius, zone_C_radius * 2, zone_C_radius * 2);
    ctx.beginPath();
    ctx.arc(0, 0, zone_C_radius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.restore();

    // draw zone D
    ctx.fillStyle = "#00DEE6";
    ctx.lineWidth = 15;
    var grip_dist = Math.floor(angles[MOTOR_6] / grip_max_angle * zone_D_height);
    ctx.fillRect(zone_D_center_x - zone_D_width / 2, zone_D_center_y - zone_D_height / 2, zone_D_width, zone_D_height);

    ctx.lineWidth = 1;
    ctx.strokeStyle = "#FFFFFF";

    var center_x = zone_D_center_x;
    var center_y = zone_D_center_y + grip_dist / 2;

    var grd = ctx.createLinearGradient(center_x, center_y, center_x, center_y  + grip_height);
    grd.addColorStop(0,"#004A4D");
    grd.addColorStop(1,"#b3fcff");
    ctx.fillStyle = grd;
    ctx.beginPath();
    ctx.moveTo(center_x + grip_width / 2, center_y);
    ctx.bezierCurveTo(center_x + grip_width / 4, center_y + grip_height, center_x - grip_width / 4, center_y + grip_height,center_x - grip_width / 2, center_y);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    center_x = zone_D_center_x;
    center_y = zone_D_center_y - grip_dist / 2;

    grd = ctx.createLinearGradient(center_x, center_y, center_x, center_y  - grip_height);
    grd.addColorStop(0,"#004A4D");
    grd.addColorStop(1,"#b3fcff");
    ctx.fillStyle = grd;
    ctx.beginPath();
    ctx.moveTo(center_x + grip_width / 2, center_y);
    ctx.bezierCurveTo(center_x + grip_width / 4, center_y - grip_height, center_x - grip_width / 4, center_y - grip_height,center_x - grip_width / 2, center_y);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    ctx.restore();

    // draw sole
    ctx.fillStyle = "#006468";
    ctx.fillRect(-SOLE_WIDTH/2, -SOLE_HEIGHT, SOLE_WIDTH, SOLE_HEIGHT);
}
function event_handler(event)
{
    var x, y, r, alpha;
    // convert coordinate
    if(event.touches)
    {
        var touches = event.touches;

        x = (touches[0].pageX - touches[0].target.offsetLeft) - pivot_x;
        y = (touches[0].pageY - touches[0].target.offsetTop) - pivot_y;

    }
    else
    {
        x = event.offsetX - pivot_x;
        y = event.offsetY - pivot_y;
    }
    x = -x;
    y = -y;

    //check whether it's located in zone A or not
    r = Math.sqrt(x * x + y * y);

    if(r < zone_A_radius && y > -SOLE_HEIGHT)
    {
        if((x < SOLE_WIDTH/2) && (x > -SOLE_WIDTH/2) && (y < 0))
            return false;

        alpha = Math.atan2(y, x);

        if(alpha < 0)
            alpha += 2*Math.PI;

        mouse_xyra.x = x;
        mouse_xyra.y = y;
        mouse_xyra.r = r;
        mouse_xyra.a = alpha;

        if(geometric_calculation())
            return true;
    }

    //check whether it's located in zone B or not
    temp_x = x - zone_B_center_x;
    temp_y = y - zone_B_center_y;
    var distance = Math.sqrt(temp_x * temp_x + temp_y * temp_y);

    if(distance < zone_B_radius)
    {
        var angle = Math.atan2(temp_y, temp_x)* 180 / Math.PI;

        if(click_state == 0)
            zone_B_last_angle = angle;
        else
        {
            if((Math.abs(angle) > 90) && (angle * zone_B_last_angle < 0))
            {
                if(zone_B_last_angle > 0)
                    zone_B_last_angle = -180;
                else
                    zone_B_last_angle = 180;
            }

            angles[MOTOR_1] += Math.floor(angle - zone_B_last_angle);

            angles[MOTOR_1] = Math.max(0, angles[MOTOR_1]);
            angles[MOTOR_1] = Math.min(180, angles[MOTOR_1]);

            zone_B_last_angle = angle;
        }
        return true;
    }

    //check whether it's located in zone C or not
    temp_x = x - zone_C_center_x;
    temp_y = y - zone_C_center_y;
    var distance = Math.sqrt(temp_x * temp_x + temp_y * temp_y);

    if(distance < zone_C_radius)
    {
        var angle = Math.atan2(temp_y, temp_x)* 180 / Math.PI;

        if(click_state == 0)
            zone_C_last_angle = angle;
        else
        {
            if((Math.abs(angle) > 90) && (angle * zone_C_last_angle < 0))
            {
                if(zone_C_last_angle > 0)
                    zone_C_last_angle = -180;
                else
                    zone_C_last_angle = 180;
            }

            angles[MOTOR_5] += Math.floor(angle - zone_C_last_angle);

            angles[MOTOR_5] = Math.max(0, angles[MOTOR_5]);
            angles[MOTOR_5] = Math.min(180, angles[MOTOR_5]);

            zone_C_last_angle = angle;
        }
        return true;
    }

    //check whether it's located in zone D or not
    var temp_x = Math.abs(x - zone_D_center_x);
    var temp_y = Math.abs(y - zone_D_center_y);

    if(temp_x < (zone_D_width / 2) && temp_y < (zone_D_height / 2))
    {
        var angle = temp_y / (zone_D_height / 2) * grip_max_angle;
        angles[MOTOR_6] = Math.floor(angle);
        console.log(angles[MOTOR_6]);
        return true;
    }

    return false;
}
function geometric_calculation()
{
    var c_ = c - Math.pow(mouse_xyra.r, 2);
    var delta = b*b - 4*a*c_;
    if(delta < 0)
        return false; // no root

    var x1 = 0, x2 = 0;
    var x = 0;
    var cos_C = 0;
    var alpha = 0, beta = 0, gamma = 0;

    x1 = (-b + Math.sqrt(delta)) / (2*a);
    x2 = (-b - Math.sqrt(delta)) / (2*a);
    x = x1;

    if(x > 1)
        return false;

    alpha = Math.acos(x);
    alpha = alpha * 180 / Math.PI;
    beta = 180 - alpha;
    cos_C = Math.pow(mouse_xyra.r, 2) + Math.pow(ARM_LENGTH_1, 2) - ( Math.pow(ARM_LENGTH_2, 2) + Math.pow(ARM_LENGTH_3, 2) + 2*ARM_LENGTH_2*ARM_LENGTH_3*x );

    cos_C = cos_C /(2*mouse_xyra.r*ARM_LENGTH_1);
    angle_C = Math.acos(cos_C);
    gamma = (angle_C + mouse_xyra.a) % (2*Math.PI);
    gamma = gamma * 180 / Math.PI;

    if(gamma < 0)
        gamma += 360;

    if(gamma > 180)
    {
        var temp = gamma -  mouse_xyra.a * 180 / Math.PI;
        gamma = gamma - 2* temp;
        beta = 360 - beta;
    }

    if(gamma < 0 || gamma > 180)
        return false;

    angles[MOTOR_3] = Math.floor(beta);
    angles[MOTOR_4] = Math.floor(beta);
    angles[MOTOR_2] = Math.floor(gamma);

    return true;
}
function draw_pivot(x, y)
{
    ctx.beginPath();
    ctx.arc(x,y, 10, 0, 2*Math.PI);
    ctx.stroke();
    ctx.fill();
}
function ws_onmessage(e_msg)
{
    e_msg = e_msg || window.event; // MessageEvent
}
function ws_onopen()
{
    document.getElementById("ws_state").innerHTML = "OPEN";
    document.getElementById("wc_conn").innerHTML = "Disconnect";
    angles_change_notice();

    update_view();
}
function ws_onclose()
{
    document.getElementById("ws_state").innerHTML = "CLOSED";
    document.getElementById("wc_conn").innerHTML = "Connect";
    console.log("socket was closed");
    ws.onopen = null;
    ws.onclose = null;
    ws.onmessage = null;
    ws = null;
}
function wc_onclick()
{
    if(ws == null)
    {
        ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/remote_arm", "text.phpoc");
        document.getElementById("ws_state").innerHTML = "CONNECTING";

        ws.onopen = ws_onopen;
        ws.onclose = ws_onclose;
        ws.onmessage = ws_onmessage;
    }
    else
        ws.close();
}
function mouse_down()
{
    if(ws == null)
        return;

    if(event.touches && (event.touches.length > 1))
        click_state = event.touches.length;

    if(click_state > 1)
        return;

    var state = event_handler(event);
    if(state)
    {
        click_state = 1;
        angles_change_notice();
    }

    event.preventDefault();
}
function mouse_up()
{
    click_state = 0;
}
function mouse_move()
{
    if(ws == null)
        return;

    var d = new Date();
    var time = d.getTime();
    if((time - last_time) < 50)
        return;

    last_time = time;

    if(event.touches && (event.touches.length > 1))
        click_state = event.touches.length;

    if(click_state > 1)
        return;

    if(!click_state)
        return;

    var state = event_handler(event);
    if(state)
    {
        click_state = 1;
        angles_change_notice();
    }

    event.preventDefault();
}
function angles_change_notice()
{
    if(ws != null && ws.readyState == 1)
    {
        ws.send(angles.join(",") + "\r\n");
        update_view();
    }
}
window.onload = init;
setTimeout(function(){ update_view();}, 500);
</script>
</head>
<body>
<h2>Arduino - Control Arm Robot via Web</h2>
<canvas id="servo"></canvas>
<p>WebSocket : <span id="ws_state">null</span><br></p>
<button id="wc_conn" type="button" onclick="wc_onclick();">Connect</button>
</body>
</html>
