jQuery(document).ready(function(a){("leter"==pagenow||"letter"==pagenow)&&a(".postarea").hide().after('<div id="contentToggle"><a href="#">'+last_letter.showContent+"</a></div>");a("#contentToggle").click(function(){a(".postarea").is(":hidden")?a("#contentToggle").fadeOut("slow",function(){a(".postarea").fadeIn();a("#contentToggle").addClass("minimized");a("#contentToggle a").text(last_letter.hideContent);a("#contentToggle").fadeIn()}):(a("#contentToggle").fadeOut(),a(".postarea").fadeOut("slow",
function(){a("#contentToggle a").text(last_letter.showContent);a("#contentToggle").removeClass("minimized");a("#contentToggle").fadeIn()}))});a("#newRecipient").click(function(){a(".recipientDiv:last").after(a(".recipientDiv:last").clone());a(".recipientDiv:last input").val("");return!1});a(".recipientDiv").not(":last").hover(function(){a(this).children(".recipientRemove").fadeIn()},function(){a(this).children(".recipientRemove").fadeOut()});a(".recipientRemove a").click(function(){a(this).parent().parent().remove();
return!1})});