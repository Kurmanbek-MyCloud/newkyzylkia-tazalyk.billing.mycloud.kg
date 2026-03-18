$(document).ready(function () {
    const width = window.innerWidth - 100;

    // console.log(width);
    // console.log($("img"));
    const imgs = document.querySelectorAll(".floor-image");
    for (const img of imgs) {
        // console.log($(img));
        $(".floor-scheme").css({
            "top": $(img)[0].clientTop + "px",
            "left": +$(img)[0].clientLeft + "px"
            // "left"
        })

    }
    // $("#scheme").width(width);
    // $("floor-scheme").
})
// $(document).ready(function(){
//     var svg = document.querySelector('#floor-scheme');
//     var real_coards = svg.createSVGPoint();
//     var object = document.getElementById('card_info'),
//         X = 0,
//         Y = 0
//     mouseX = 0,
//         mouseY = 0;
//     window.addEventListener("mousemove", function(ev) {
//         ev = window.event || ev;
//         X = ev.pageX;
//         Y = ev.pageY;
//         let coards =cursorPoint(X,Y);
//         cardMove(coards);
//     });

//     function cardMove(data) {
//         var p = "px";
//         let x=data.x;
//         let y=data.y;
//         $(object).css("left",x + p);
//         $(object).css("top",y + p);
//     }
//     function cursorPoint(xStart, yStart) {
//         real_coards.x = xStart;
//         real_coards.y = yStart;
//         return real_coards.matrixTransform(svg.getScreenCTM().inverse());
//     }
// })