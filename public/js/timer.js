var Timer;
var TotalSeconds;

function CreateTimer(TimerID, Time) {
  	Timer = document.getElementById(TimerID);
  	TotalSeconds = Time;
  	
  	UpdateTimer();
	window.setTimeout("Tick()", 1000);
}

function Tick() {
  	if (TotalSeconds <= 0) {
  	  	document.forms[0].submit();
  	  	return;
  	}
  	TotalSeconds -= 1;
  	UpdateTimer();
  	window.setTimeout("Tick()", 1000);
}

function UpdateTimer() {
	var Seconds = TotalSeconds;
	var Hours = Math.floor(Seconds/3600);
	Seconds -= Hours*(3600);
	var Minutes = Math.floor(Seconds/60);
	Seconds -= Minutes*(60);
	var TimeStr = (LeadingZero(Hours) + ":" + LeadingZero(Minutes) + ":" + LeadingZero(Seconds));
	Timer.innerHTML = "Time Remaining: " + TimeStr;
	document.forms[0].elements['timeRemaining'].value = TotalSeconds;
	
	
}
function LeadingZero(Time) {
  	return (Time < 10) ? "0" + Time : + Time;
}